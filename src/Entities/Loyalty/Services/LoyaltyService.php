<?php

namespace Meva\Entities\Loyalty\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;
use Meva\Entities\Loyalty\Models\LoyaltyCoupon;
use Meva\Entities\Loyalty\Models\LoyaltyEntry;
use Meva\Entities\User\Models\User;

/**
 * Meva Klub: points for delivered orders, tiers for loyal customers, and
 * coupons bought with points.
 *
 * Points live in a ledger (LoyaltyEntry). A member's balance is the sum of
 * their rows; what they have ever earned -- which sets their tier -- is the
 * same sum without the points they spent. Points for an order on its way are
 * never stored: they are worked out from the order when asked for, so a
 * cancelled parcel simply stops counting.
 */
class LoyaltyService
{
    /**
     * Statuses whose orders count as points on the way.
     */
    public const PENDING_STATUSES = ['awaiting-dispatch', 'dispatched'];

    /**
     * Statuses that undo what a delivered order earned.
     */
    public const REVERSING_STATUSES = ['returned', 'cancelled'];

    /**
     * Letters and digits a customer cannot misread: no 0/O, 1/I.
     */
    protected const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /*
    |--------------------------------------------------------------------------
    | Balance and tier
    |--------------------------------------------------------------------------
    */

    public function balance(User $user): int
    {
        return (int) LoyaltyEntry::query()->where('user_id', $user->id)->sum('points');
    }

    /**
     * Everything the member has earned, spent or not.
     *
     * Reversals count against it: a returned order should not keep a customer
     * in a tier it bought them.
     */
    public function lifetime(User $user): int
    {
        $sum = (int) LoyaltyEntry::query()
            ->where('user_id', $user->id)
            ->where('reason', '!=', LoyaltyEntry::REDEEM)
            ->sum('points');

        return max(0, $sum);
    }

    /**
     * @return array<int, array{key: string, label: string, at: int, multiplier: float}>
     */
    public function tiers(): array
    {
        return collect(config('loyalty.tiers'))
            ->map(fn (array $tier): array => [
                'key' => $tier['key'],
                'label' => $tier['label'],
                'at' => (int) $tier['at'],
                'multiplier' => (float) $tier['multiplier'],
            ])
            ->sortBy('at')
            ->values()
            ->all();
    }

    /**
     * The tier a lifetime total reaches.
     *
     * @return array{key: string, label: string, at: int, multiplier: float}
     */
    public function tierFor(int $lifetime): array
    {
        $reached = $this->tiers()[0];

        foreach ($this->tiers() as $tier) {
            if ($lifetime >= $tier['at']) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    /**
     * The tier after this one, or null at the top.
     */
    public function nextTier(array $tier): ?array
    {
        return collect($this->tiers())->first(fn (array $t): bool => $t['at'] > $tier['at']);
    }

    public function tierOf(User $user): array
    {
        return $this->tierFor($this->lifetime($user));
    }

    /**
     * What the login and profile responses carry.
     *
     * @return array{points: int, tier: string}
     */
    public function brief(User $user): array
    {
        return [
            'points' => $this->balance($user),
            'tier' => $this->tierOf($user)['key'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Earning
    |--------------------------------------------------------------------------
    */

    /**
     * Points an order total earns at a multiplier: one per full 100 RSD, then
     * the multiplier, rounded down.
     *
     * The multiplier is taken as a whole percentage so 1.25 × 3 is 3, not the
     * 3.7499999 a float would make of it.
     */
    public function pointsFor(int $total, float $multiplier): int
    {
        $base = intdiv(max(0, $total), (int) config('loyalty.earn_per', 10000));

        return intdiv($base * (int) round($multiplier * 100), 100);
    }

    /**
     * What an order of this total would earn this member on delivery, at
     * their tier as it stands. Nothing for a guest.
     */
    public function estimate(?User $user, int $total): int
    {
        return $user === null ? 0 : $this->pointsFor($total, $this->tierOf($user)['multiplier']);
    }

    /**
     * Points on orders that have been placed but not yet delivered.
     */
    public function pendingPoints(User $user): int
    {
        $multiplier = $this->tierOf($user)['multiplier'];

        return (int) $this->ordersOf($user)
            ->whereIn('status', self::PENDING_STATUSES)
            ->pluck('total')
            ->sum(fn ($total): int => $this->pointsFor((int) $total, $multiplier));
    }

    /**
     * A member's placed orders: theirs by account, or by the e-mail they
     * were placed with -- the same matching the account's order list uses.
     */
    protected function ordersOf(User $user)
    {
        return DB::table('lunar_orders')
            ->whereNotNull('placed_at')
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhere('customer_reference', mb_strtolower($user->email)));
    }

    /**
     * Whose points an order earns.
     */
    public function ownerOf(Order $order): ?User
    {
        if ($order->user_id) {
            return User::query()->find($order->user_id);
        }

        if ($order->customer_reference) {
            return User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower(trim($order->customer_reference))])
                ->first();
        }

        return null;
    }

    /**
     * The welcome gift, once per account.
     */
    public function welcome(User $user): void
    {
        $given = LoyaltyEntry::query()
            ->where('user_id', $user->id)
            ->where('reason', LoyaltyEntry::WELCOME)
            ->exists();

        if (! $given) {
            LoyaltyEntry::query()->create([
                'user_id' => $user->id,
                'points' => (int) config('loyalty.welcome_bonus', 100),
                'reason' => LoyaltyEntry::WELCOME,
            ]);
        }
    }

    /**
     * Bring the ledger in line with an order's status.
     *
     * Delivered: credit what it earns, once. Returned or cancelled after that:
     * take back exactly what was credited, once. Anything else: nothing --
     * points on the way are worked out, not stored.
     *
     * Safe to call on every save: the (order, reason) unique index turns a
     * second credit into a no-op even if two requests race.
     */
    public function syncOrder(Order $order): void
    {
        if ($order->status === 'delivered') {
            $this->credit($order);
        } elseif (in_array($order->status, self::REVERSING_STATUSES, true)) {
            $this->reverse($order);
        }
    }

    protected function credit(Order $order): void
    {
        if (LoyaltyEntry::query()->where('order_id', $order->id)->where('reason', LoyaltyEntry::ORDER)->exists()) {
            return;
        }

        $user = $this->ownerOf($order);

        if ($user === null) {
            return;
        }

        $points = $this->pointsFor((int) $order->getRawOriginal('total'), $this->tierOf($user)['multiplier']);

        if ($points <= 0) {
            return;
        }

        $this->insertOnce([
            'user_id' => $user->id,
            'points' => $points,
            'reason' => LoyaltyEntry::ORDER,
            'order_id' => $order->id,
        ]);
    }

    protected function reverse(Order $order): void
    {
        $credit = LoyaltyEntry::query()
            ->where('order_id', $order->id)
            ->where('reason', LoyaltyEntry::ORDER)
            ->first();

        if ($credit === null) {
            return;
        }

        $this->insertOnce([
            'user_id' => $credit->user_id,
            'points' => -$credit->points,
            'reason' => LoyaltyEntry::REVERSAL,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Write a ledger row the unique index may already hold.
     *
     * The savepoint keeps a duplicate from aborting the caller's transaction
     * (PostgreSQL refuses anything further in a transaction that has errored).
     */
    protected function insertOnce(array $attributes): void
    {
        $exists = LoyaltyEntry::query()
            ->where('order_id', $attributes['order_id'])
            ->where('reason', $attributes['reason'])
            ->exists();

        if ($exists) {
            return;
        }

        try {
            DB::transaction(fn () => LoyaltyEntry::query()->create($attributes));
        } catch (UniqueConstraintViolationException) {
            // Another request got there first; the order is already settled.
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Rewards and coupons
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array{key: string, label: string, points: int, value: int}>
     */
    public function rewards(): array
    {
        return collect(config('loyalty.rewards'))
            ->map(fn (array $reward): array => [
                'key' => $reward['key'],
                'label' => $reward['label'],
                'points' => (int) $reward['points'],
                'value' => (int) $reward['value'],
            ])
            ->values()
            ->all();
    }

    public function reward(string $key): ?array
    {
        return collect($this->rewards())->firstWhere('key', $key);
    }

    /**
     * Trade points for a coupon.
     *
     * The member's row is locked for the length of it, so two taps on the
     * button cannot both spend the same points.
     *
     * @throws ValidationException
     */
    public function redeem(User $user, string $key): LoyaltyCoupon
    {
        $reward = $this->reward($key);

        if ($reward === null) {
            throw ValidationException::withMessages(['reward' => ['Ta nagrada ne postoji.']]);
        }

        return DB::transaction(function () use ($user, $reward): LoyaltyCoupon {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->balance($user) < $reward['points']) {
                throw ValidationException::withMessages(['reward' => ['Nemate dovoljno poena za ovu nagradu.']]);
            }

            $coupon = LoyaltyCoupon::query()->create([
                'user_id' => $user->id,
                'code' => $this->newCode(),
                'value' => $reward['value'],
                'reward_key' => $reward['key'],
                'expires_at' => now()->addDays((int) config('loyalty.coupon_days', 90)),
            ]);

            LoyaltyEntry::query()->create([
                'user_id' => $user->id,
                'points' => -$reward['points'],
                'reason' => LoyaltyEntry::REDEEM,
                'coupon_id' => $coupon->id,
                'note' => $reward['label'],
            ]);

            return $coupon;
        });
    }

    /**
     * MEVA-XXXX-XXXX, unused so far.
     */
    protected function newCode(): string
    {
        do {
            $code = 'MEVA-'.$this->randomChars(4).'-'.$this->randomChars(4);
        } while (LoyaltyCoupon::query()->where('code', $code)->exists());

        return $code;
    }

    protected function randomChars(int $length): string
    {
        $chars = '';

        for ($i = 0; $i < $length; $i++) {
            $chars .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $chars;
    }

    /**
     * The coupon behind a code, if this member may use it now.
     *
     * With $lock the row is held until the surrounding transaction ends, so a
     * coupon cannot pay for two orders placed at the same moment.
     *
     * @throws ValidationException
     */
    public function usableCoupon(?User $user, ?string $code, bool $lock = false): LoyaltyCoupon
    {
        if ($user === null) {
            throw ValidationException::withMessages(['coupon' => ['Prijavite se da biste iskoristili kupon.']]);
        }

        $query = LoyaltyCoupon::query()->where('code', mb_strtoupper(trim((string) $code)));

        $coupon = $lock ? $query->lockForUpdate()->first() : $query->first();

        // Someone else's coupon is, to this customer, no coupon at all.
        if ($coupon === null || (int) $coupon->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['coupon' => ['Kupon nije važeći.']]);
        }

        if ($coupon->used_at !== null) {
            throw ValidationException::withMessages(['coupon' => ['Kupon je već iskorišćen.']]);
        }

        if ($coupon->expires_at->isPast()) {
            throw ValidationException::withMessages(['coupon' => ['Kupon je istekao.']]);
        }

        return $coupon;
    }

    /**
     * Take a coupon off an order and spend it.
     *
     * Lunar keeps money on the order as integers in minor units behind a
     * Price cast, which writes whatever integer it is given; the raw columns
     * are read so the cast's Price objects never reach the arithmetic. The
     * discount never exceeds the goods: a coupon does not pay for itself.
     *
     * @return int The discount, in minor units.
     */
    public function applyCoupon(Order $order, LoyaltyCoupon $coupon): int
    {
        $subTotal = (int) $order->getRawOriginal('sub_total');
        $discount = min($coupon->value, $subTotal);

        $order->discount_total = (int) $order->getRawOriginal('discount_total') + $discount;
        $order->total = max(0, (int) $order->getRawOriginal('total') - $discount);
        $order->save();

        $coupon->update(['used_at' => now(), 'order_id' => $order->id]);

        return $discount;
    }
}

<?php

namespace Meva\Api\V1\Controllers\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Meva\Api\V1\Transformers\Loyalty\LoyaltyCouponTransformer;
use Meva\Api\V1\Transformers\Loyalty\LoyaltyEntryTransformer;
use Meva\Entities\Loyalty\Models\LoyaltyCoupon;
use Meva\Entities\Loyalty\Models\LoyaltyEntry;
use Meva\Entities\Loyalty\Services\LoyaltyService;

/**
 * Meva Klub, from the member's side: their points, their tier, what the points
 * buy, and the coupons they have bought.
 */
class LoyaltyController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty)
    {
    }

    /**
     * Everything the loyalty screen shows, in one request.
     */
    public function show()
    {
        $user = auth()->user();

        $points = $this->loyalty->balance($user);
        $lifetime = $this->loyalty->lifetime($user);
        $tier = $this->loyalty->tierFor($lifetime);
        $next = $this->loyalty->nextTier($tier);

        $coupons = LoyaltyCoupon::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $history = LoyaltyEntry::query()
            ->where('user_id', $user->id)
            ->with('order:id,reference')
            ->latest('id')
            ->limit(50)
            ->get();

        $couponTransformer = new LoyaltyCouponTransformer;
        $entryTransformer = new LoyaltyEntryTransformer;

        return $this->response->array([
            'data' => [
                'points' => $points,
                'pending_points' => $this->loyalty->pendingPoints($user),
                'lifetime_points' => $lifetime,
                'tier' => [
                    'key' => $tier['key'],
                    'label' => $tier['label'],
                    'multiplier' => $tier['multiplier'],
                    'next' => $next === null ? null : [
                        'key' => $next['key'],
                        'label' => $next['label'],
                        'at' => $next['at'],
                        'points_needed' => max(0, $next['at'] - $lifetime),
                    ],
                    'progress' => $next === null
                        ? 1.0
                        : round(min(1, max(0, ($lifetime - $tier['at']) / ($next['at'] - $tier['at']))), 4),
                ],
                'tiers' => $this->loyalty->tiers(),
                'rewards' => collect($this->loyalty->rewards())
                    ->map(fn (array $reward): array => $reward + [
                        'value_formatted' => OrderTransformer::money($reward['value']),
                        'affordable' => $points >= $reward['points'],
                    ])
                    ->all(),
                'coupons' => $coupons->map(fn (LoyaltyCoupon $c): array => $couponTransformer->transform($c))->all(),
                'history' => $history->map(fn (LoyaltyEntry $e): array => $entryTransformer->transform($e))->all(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Trade points for a coupon.
     */
    public function redeem(Request $request)
    {
        $request->validate(['reward' => 'required|string|max:60'], [
            'reward.required' => 'Izaberite nagradu.',
        ]);

        $user = auth()->user();
        $coupon = $this->loyalty->redeem($user, $request->input('reward'));

        return $this->response->array([
            'data' => [
                'coupon' => (new LoyaltyCouponTransformer)->transform($coupon),
                'points' => $this->loyalty->balance($user),
            ],
        ])->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Check a coupon before the order is placed, so checkout can show the
     * discount. Nothing is spent here.
     */
    public function coupon(Request $request)
    {
        // Every answer about a coupon, a missing code included, comes back
        // under `coupon`, which is where checkout shows it.
        if (! is_string($request->input('code')) || trim($request->input('code')) === '') {
            throw ValidationException::withMessages(['coupon' => ['Unesite kod kupona.']]);
        }

        $coupon = $this->loyalty->usableCoupon(auth()->user(), $request->input('code'));

        return $this->response->array([
            'data' => [
                'code' => $coupon->code,
                'value' => (int) $coupon->value,
                'value_formatted' => OrderTransformer::money((int) $coupon->value),
                'valid' => true,
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }
}

<?php

use Database\Seeders\AccessSeeder;
use Illuminate\Http\Response;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Meva\Entities\Loyalty\Models\LoyaltyCoupon;
use Meva\Entities\Loyalty\Models\LoyaltyEntry;
use Meva\Entities\Loyalty\Services\LoyaltyService;
use Meva\Entities\User\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Meva Klub
|--------------------------------------------------------------------------
|
| Orders are placed through the real checkout, so the points, the coupon and
| the order totals are checked end to end. The suite signs in a super-admin
| for every test; these sign in as a customer instead, or as nobody.
|
*/

beforeEach(function () {
    $type = ProductType::factory()->create(['name' => 'Simple']);
    $product = Product::factory()->create(['product_type_id' => $type->id, 'status' => 'published']);

    // 2.500 RSD a jar: 25 points at the first tier.
    $variant = $product->variants()->create([
        'sku' => 'KREMA-1',
        'stock' => 100,
        'tax_class_id' => TaxClass::getDefault()?->id,
    ]);

    $variant->prices()->create([
        'price' => 250000,
        'currency_id' => Currency::getDefault()->id,
        'min_quantity' => 1,
    ]);
});

/**
 * Sign in as this customer: a Bearer token, and nothing left over from the
 * super-admin the suite starts with or the previous request's token, which
 * JWTAuth otherwise keeps for the life of the test.
 */
function signInAs(User $user): array
{
    asGuest();

    return ['HTTP_Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
}

function asGuest(): array
{
    auth()->forgetGuards();
    JWTAuth::unsetToken();
    app('tymon.jwt')->unsetToken();

    return [];
}

function member(string $email = 'ana@example.com'): User
{
    return User::factory()->create([User::EMAIL => $email]);
}

/**
 * Place an order through the storefront.
 */
function placeOrder(array $headers, int $quantity = 1, array $extra = [])
{
    return test()->post(url('/api/shop/orders'), array_merge([
        'lines' => [['sku' => 'KREMA-1', 'quantity' => $quantity]],
        'customer' => [
            'first_name' => 'Ana',
            'last_name' => 'Petrović',
            'phone' => '0601234567',
            'email' => 'ana@example.com',
            'address' => 'Knez Mihailova 1',
            'city' => 'Beograd',
            'postcode' => '11000',
        ],
    ], $extra), array_merge(['Accept' => 'application/json'], $headers));
}

function orderFrom($response): Order
{
    return Order::query()->where('reference', $response->json('data.reference'))->sole();
}

function giveCoupon(User $user, array $attributes = []): LoyaltyCoupon
{
    return LoyaltyCoupon::query()->create(array_merge([
        'user_id' => $user->id,
        'code' => 'MEVA-ABCD-EFGH',
        'value' => 30000,
        'reward_key' => 'popust-300',
        'expires_at' => now()->addDays(90),
    ], $attributes));
}

function givePoints(User $user, int $points, string $reason = LoyaltyEntry::ADJUSTMENT): void
{
    LoyaltyEntry::query()->create(['user_id' => $user->id, 'points' => $points, 'reason' => $reason]);
}

/*
|--------------------------------------------------------------------------
| Earning
|--------------------------------------------------------------------------
*/

it('gives a welcome bonus to a new account', function () {
    // Registration hands out the customer role, which the test seed lacks.
    $this->seed(AccessSeeder::class);
    asGuest();

    $response = $this->post(url('/api/account/register'), [
        'first_name' => 'Mila',
        'last_name' => 'Jovanović',
        'email' => 'mila@example.com',
        'password' => 'tajna-lozinka-123',
        'password_confirmation' => 'tajna-lozinka-123',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_CREATED);

    $user = User::query()->where('email', 'mila@example.com')->sole();

    expect(app(LoyaltyService::class)->balance($user))->toBe(100)
        ->and(LoyaltyEntry::query()->where('user_id', $user->id)->sole()->reason)->toBe('welcome')
        ->and($response->json('user.loyalty'))->toBe(['points' => 100, 'tier' => 'pupoljak']);
});

it('counts an order on its way as pending, and credits it once on delivery', function () {
    $user = member();
    $headers = signInAs($user);

    $placed = placeOrder($headers, 3); // 7.500 RSD
    $placed->assertStatus(Response::HTTP_CREATED);

    expect($placed->json('data.points_estimate'))->toBe(75);

    $order = orderFrom($placed);
    $loyalty = app(LoyaltyService::class);

    expect($loyalty->pendingPoints($user))->toBe(75)
        ->and($loyalty->balance($user))->toBe(0);

    $order->update(['status' => 'dispatched']);
    expect($loyalty->pendingPoints($user))->toBe(75);

    $order->update(['status' => 'delivered']);

    expect($loyalty->balance($user))->toBe(75)
        ->and($loyalty->pendingPoints($user))->toBe(0);

    // Saved again, or moved off and back: still credited once.
    $order->update(['status' => 'dispatched']);
    $order->update(['status' => 'delivered']);
    app(LoyaltyService::class)->syncOrder($order->refresh());

    expect(LoyaltyEntry::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($loyalty->balance($user))->toBe(75);
});

it('credits delivery through the back office at the member\'s tier', function () {
    $this->seed(AccessSeeder::class);

    $user = member();
    givePoints($user, 500); // Cvet: × 1.25

    $order = orderFrom(placeOrder(signInAs($user), 3)); // 75 base points

    $response = $this->put(url("/api/admin/orders/{$order->id}/status"), ['status' => 'delivered'], signInAs($this->authenticatedUser));

    $response->assertStatus(Response::HTTP_OK);

    $credit = LoyaltyEntry::query()->where('order_id', $order->id)->sole();

    // 75 × 1.25 = 93.75, rounded down.
    expect($credit->points)->toBe(93)
        ->and($credit->reason)->toBe('order')
        ->and($credit->user_id)->toBe($user->id);
});

it('credits a guest order placed with a member\'s e-mail', function () {
    $user = member('ana@example.com');

    $order = orderFrom(placeOrder(asGuest()));
    expect($order->user_id)->toBeNull();

    $order->update(['status' => 'delivered']);

    expect(app(LoyaltyService::class)->balance($user))->toBe(25);
});

it('takes the points back when a delivered order is returned', function () {
    $user = member();
    $order = orderFrom(placeOrder(signInAs($user), 2));

    $order->update(['status' => 'delivered']);
    $order->update(['status' => 'returned']);
    $order->update(['status' => 'cancelled']);

    $loyalty = app(LoyaltyService::class);

    expect($loyalty->balance($user))->toBe(0)
        ->and(LoyaltyEntry::query()->where('order_id', $order->id)->where('reason', 'reversal')->sole()->points)->toBe(-50)
        ->and($loyalty->lifetime($user))->toBe(0);
});

it('does nothing when an order is cancelled before delivery', function () {
    $user = member();
    $order = orderFrom(placeOrder(signInAs($user)));

    $order->update(['status' => 'cancelled']);

    expect(LoyaltyEntry::query()->count())->toBe(0)
        ->and(app(LoyaltyService::class)->pendingPoints($user))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Redeeming
|--------------------------------------------------------------------------
*/

it('trades points for a coupon', function () {
    $user = member();
    givePoints($user, 250);

    $response = $this->post(url('/api/account/loyalty/redeem'), ['reward' => 'popust-300'], signInAs($user));

    $response->assertStatus(Response::HTTP_CREATED);

    $coupon = LoyaltyCoupon::query()->sole();

    expect($response->json('data.points'))->toBe(50)
        ->and($response->json('data.coupon.code'))->toBe($coupon->code)
        ->and($coupon->code)->toMatch('/^MEVA-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
        ->and($response->json('data.coupon.value'))->toBe(30000)
        ->and($response->json('data.coupon.value_formatted'))->toBe('300 RSD')
        ->and($response->json('data.coupon.status'))->toBe('active')
        ->and($response->json('data.coupon.used_at'))->toBeNull()
        ->and($coupon->expires_at->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and(LoyaltyEntry::query()->where('reason', 'redeem')->sole()->points)->toBe(-200);
});

it('refuses a reward the member cannot afford', function () {
    $user = member();
    givePoints($user, 150);

    $response = $this->post(url('/api/account/loyalty/redeem'), ['reward' => 'popust-300'], signInAs($user));

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    expect($response->json('message'))->toBe('Nemate dovoljno poena za ovu nagradu.')
        ->and($response->json('errors.reward.0'))->toBe('Nemate dovoljno poena za ovu nagradu.')
        ->and(LoyaltyCoupon::query()->count())->toBe(0)
        ->and(app(LoyaltyService::class)->balance($user))->toBe(150);
});

it('refuses a reward that does not exist', function () {
    $user = member();
    givePoints($user, 5000);

    $response = $this->post(url('/api/account/loyalty/redeem'), ['reward' => 'popust-9000'], signInAs($user));

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect($response->json('errors.reward'))->not->toBeEmpty();
});

it('keeps lifetime points, and the tier, after spending', function () {
    $user = member();
    givePoints($user, 600);

    $this->post(url('/api/account/loyalty/redeem'), ['reward' => 'popust-700'], signInAs($user))
        ->assertStatus(Response::HTTP_CREATED);

    $loyalty = app(LoyaltyService::class);

    expect($loyalty->balance($user))->toBe(200)
        ->and($loyalty->lifetime($user))->toBe(600)
        ->and($loyalty->tierOf($user)['key'])->toBe('cvet');
});

/*
|--------------------------------------------------------------------------
| Coupons at checkout
|--------------------------------------------------------------------------
*/

it('takes a coupon off the order and spends it', function () {
    $user = member();
    $coupon = giveCoupon($user);

    $response = placeOrder(signInAs($user), 1, ['coupon' => 'meva-abcd-efgh']);

    $response->assertStatus(Response::HTTP_CREATED);

    $order = orderFrom($response);
    $coupon->refresh();

    expect($response->json('data.total'))->toBe(220000)
        ->and($response->json('data.total_formatted'))->toBe('2.200 RSD')
        ->and($response->json('data.discount_total'))->toBe(30000)
        // Earned on what is actually paid: 2.200 RSD.
        ->and($response->json('data.points_estimate'))->toBe(22)
        ->and(minorUnits($order->total))->toBe(220000)
        ->and(minorUnits($order->discount_total))->toBe(30000)
        ->and(minorUnits($order->sub_total))->toBe(250000)
        ->and($coupon->used_at)->not->toBeNull()
        ->and($coupon->order_id)->toBe($order->id);

    $order->update(['status' => 'delivered']);
    expect(app(LoyaltyService::class)->balance($user))->toBe(22);
});

it('never discounts more than the goods', function () {
    $user = member();
    giveCoupon($user, ['value' => 1000000]);

    $response = placeOrder(signInAs($user), 1, ['coupon' => 'MEVA-ABCD-EFGH']);

    $response->assertStatus(Response::HTTP_CREATED);

    expect($response->json('data.total'))->toBe(0)
        ->and($response->json('data.discount_total'))->toBe(250000)
        ->and($response->json('data.points_estimate'))->toBe(0);
});

it('refuses a coupon', function (Closure $setup, string $message) {
    $owner = member();
    [$headers] = $setup($owner);

    $response = placeOrder($headers, 1, ['coupon' => 'MEVA-ABCD-EFGH']);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    expect($response->json('errors.coupon.0'))->toBe($message)
        // Nothing half-placed.
        ->and(Order::query()->whereNotNull('placed_at')->count())->toBe(0);
})->with([
    'belonging to someone else' => [function (User $owner) {
        giveCoupon($owner);

        return [signInAs(member('drugi@example.com'))];
    }, 'Kupon nije važeći.'],
    'already used' => [function (User $owner) {
        giveCoupon($owner, ['used_at' => now()->subDay()]);

        return [signInAs($owner)];
    }, 'Kupon je već iskorišćen.'],
    'expired' => [function (User $owner) {
        giveCoupon($owner, ['expires_at' => now()->subDay()]);

        return [signInAs($owner)];
    }, 'Kupon je istekao.'],
    'from a guest' => [function (User $owner) {
        giveCoupon($owner);

        return [asGuest()];
    }, 'Prijavite se da biste iskoristili kupon.'],
    'unknown' => [fn (User $owner) => [signInAs($owner)], 'Kupon nije važeći.'],
]);

it('previews a coupon before checkout', function () {
    $user = member();
    giveCoupon($user);

    $ok = $this->post(url('/api/account/loyalty/coupon'), ['code' => 'MEVA-ABCD-EFGH'], signInAs($user));

    $ok->assertStatus(Response::HTTP_OK);
    expect($ok->json('data'))->toBe([
        'code' => 'MEVA-ABCD-EFGH',
        'value' => 30000,
        'value_formatted' => '300 RSD',
        'valid' => true,
    ]);

    $bad = $this->post(url('/api/account/loyalty/coupon'), ['code' => 'MEVA-XXXX-XXXX'], signInAs($user));

    $bad->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect($bad->json('message'))->toBe('Kupon nije važeći.')
        ->and($bad->json('errors.coupon.0'))->toBe('Kupon nije važeći.')
        // Previewing spends nothing.
        ->and(LoyaltyCoupon::query()->whereNotNull('used_at')->count())->toBe(0);
});

it('keeps guest checkout working without points', function () {
    $response = placeOrder(asGuest());

    $response->assertStatus(Response::HTTP_CREATED);
    expect($response->json('data.points_estimate'))->toBe(0)
        ->and($response->json('data.discount_total'))->toBe(0)
        ->and($response->json('data.total'))->toBe(250000);
});

/*
|--------------------------------------------------------------------------
| The member's screen
|--------------------------------------------------------------------------
*/

it('shows the member their points, tier, rewards, coupons and history', function () {
    $user = member();
    givePoints($user, 100, LoyaltyEntry::WELCOME);
    givePoints($user, 500);

    $headers = signInAs($user);
    $delivered = orderFrom(placeOrder($headers, 2)); // 50 × 1.25 = 62 on delivery
    $delivered->update(['status' => 'delivered']);
    placeOrder(signInAs($user), 1); // 25 × 1.25 = 31 pending

    $this->post(url('/api/account/loyalty/redeem'), ['reward' => 'popust-300'], signInAs($user))
        ->assertStatus(Response::HTTP_CREATED);

    $response = $this->get(url('/api/account/loyalty'), signInAs($user));

    $response->assertStatus(Response::HTTP_OK);
    $data = $response->json('data');

    expect($data['points'])->toBe(462)
        ->and($data['pending_points'])->toBe(31)
        ->and($data['lifetime_points'])->toBe(662)
        ->and($data['tier'])->toMatchArray([
            'key' => 'cvet',
            'label' => 'Cvet',
            'multiplier' => 1.25,
            'next' => ['key' => 'ruza', 'label' => 'Ruža', 'at' => 1500, 'points_needed' => 838],
        ])
        ->and($data['tier']['progress'])->toEqualWithDelta(0.162, 0.0001)
        ->and($data['tiers'])->toHaveCount(3)
        ->and($data['tiers'][0])->toEqual(['key' => 'pupoljak', 'label' => 'Pupoljak', 'at' => 0, 'multiplier' => 1.0])
        ->and($data['rewards'])->toHaveCount(3)
        ->and($data['rewards'][0])->toBe([
            'key' => 'popust-300', 'label' => '300 RSD popusta', 'points' => 200, 'value' => 30000,
            'value_formatted' => '300 RSD', 'affordable' => true,
        ])
        ->and($data['rewards'][2]['affordable'])->toBeFalse()
        ->and($data['rewards'][2]['value_formatted'])->toBe('1.500 RSD')
        ->and($data['coupons'])->toHaveCount(1)
        ->and(array_keys($data['coupons'][0]))->toBe(['code', 'value', 'value_formatted', 'status', 'expires_at', 'used_at', 'created_at'])
        ->and($data['history'])->toHaveCount(4)
        ->and(array_keys($data['history'][0]))->toBe(['id', 'points', 'reason', 'label', 'order_reference', 'created_at']);

    // Newest first.
    expect(collect($data['history'])->pluck('reason')->all())->toBe(['redeem', 'order', 'adjustment', 'welcome'])
        ->and($data['history'][0]['label'])->toBe('Kupon 300 RSD popusta')
        ->and($data['history'][0]['points'])->toBe(-200)
        ->and($data['history'][1]['label'])->toBe('Porudžbina '.$delivered->reference)
        ->and($data['history'][1]['order_reference'])->toBe($delivered->reference)
        ->and($data['history'][3]['label'])->toBe('Dobrodošlica');
});

it('keeps the loyalty screen to members', function () {
    asGuest();

    $this->get(url('/api/account/loyalty'), ['Accept' => 'application/json'])
        ->assertStatus(Response::HTTP_UNAUTHORIZED);
});

it('shows the top tier as complete', function () {
    $user = member();
    givePoints($user, 2000);

    $tier = $this->get(url('/api/account/loyalty'), signInAs($user))->json('data.tier');

    expect($tier['key'])->toBe('ruza')
        ->and($tier['next'])->toBeNull()
        ->and($tier['progress'])->toEqual(1);
});

/*
|--------------------------------------------------------------------------
| The back office
|--------------------------------------------------------------------------
*/

it('summarises the programme for marketing', function () {
    $this->seed(AccessSeeder::class);

    $ana = member();
    givePoints($ana, 700);
    $coupon = app(LoyaltyService::class)->redeem($ana, 'popust-300');
    placeOrder(signInAs($ana), 1, ['coupon' => $coupon->code])->assertStatus(Response::HTTP_CREATED);

    $mila = member('mila@example.com');
    givePoints($mila, 100, LoyaltyEntry::WELCOME);

    $response = $this->get(url('/api/admin/loyalty'), signInAs($this->authenticatedUser));

    $response->assertStatus(Response::HTTP_OK);

    expect($response->json('data'))->toMatchArray([
        'members' => 2,
        'points_outstanding' => 600,
        'points_issued' => 800,
        'points_redeemed' => 200,
        'coupons_active' => 0,
        'coupons_used' => 1,
        'discount_given' => 30000,
        'discount_given_formatted' => '300 RSD',
    ])->and($response->json('data.top.0'))->toBe([
        'name' => trim($ana->first_name.' '.$ana->last_name),
        'email' => 'ana@example.com',
        'points' => 500,
        'tier' => 'cvet',
    ])->and($response->json('data.top'))->toHaveCount(2);
});

it('keeps the programme figures from people without the marketing permission', function () {
    $response = $this->get(url('/api/admin/loyalty'), signInAs(member()));

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

/**
 * Lunar hands order money back as a Price.
 */
function minorUnits(mixed $value): int
{
    return \Meva\Api\V1\Transformers\Commerce\OrderTransformer::minor($value);
}

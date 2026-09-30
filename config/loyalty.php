<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meva Klub
    |--------------------------------------------------------------------------
    |
    | The loyalty programme. A point for every full 100 RSD of a delivered
    | order, more of them the longer someone has shopped here, and points
    | traded for a coupon off the next order.
    |
    | Money is in minor units (para), as everywhere else in the API.
    |
    */

    // One point per this much of an order's total: 100 RSD.
    'earn_per' => 10000,

    // Given once, when an account is opened.
    'welcome_bonus' => 100,

    // How long a coupon may be used after it is issued.
    'coupon_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Tiers
    |--------------------------------------------------------------------------
    |
    | Reached by lifetime points -- everything ever earned, whatever has since
    | been spent -- in ascending order. The multiplier applies to what an order
    | earns when it is credited.
    |
    */

    'tiers' => [
        ['key' => 'pupoljak', 'label' => 'Pupoljak', 'at' => 0, 'multiplier' => 1.0],
        ['key' => 'cvet', 'label' => 'Cvet', 'at' => 500, 'multiplier' => 1.25],
        ['key' => 'ruza', 'label' => 'Ruža', 'at' => 1500, 'multiplier' => 1.5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rewards
    |--------------------------------------------------------------------------
    |
    | What points buy. Each is a single-use coupon worth `value` off an order.
    |
    */

    'rewards' => [
        ['key' => 'popust-300', 'label' => '300 RSD popusta', 'points' => 200, 'value' => 30000],
        ['key' => 'popust-700', 'label' => '700 RSD popusta', 'points' => 400, 'value' => 70000],
        ['key' => 'popust-1500', 'label' => '1.500 RSD popusta', 'points' => 800, 'value' => 150000],
    ],

];

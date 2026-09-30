<?php

use Meva\Api\V1\Controllers\Account\AccountController;
use Meva\Api\V1\Controllers\Account\LoyaltyController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Customer Account Routes
|--------------------------------------------------------------------------
|
| Registering, and following an order -- with an account or, for a guest, with
| the order number and the e-mail it was placed with. Members also see their
| Meva Klub points here and trade them for coupons.
|
*/

$api->version('v1', function ($api) {
    $api->group(['prefix' => 'account', 'as' => 'account'], function ($api) {
        $api->post('register', AccountController::class.'@register')->name('register');
        $api->post('track', AccountController::class.'@track')->name('track');

        $api->group(['middleware' => ['api', 'auth']], function ($api) {
            $api->get('orders', AccountController::class.'@orders')->name('orders');

            // Meva Klub: points, tier, rewards and the coupons they buy.
            $api->get('loyalty', LoyaltyController::class.'@show')->name('loyalty');
            $api->post('loyalty/redeem', LoyaltyController::class.'@redeem')->name('loyalty.redeem');
            $api->post('loyalty/coupon', LoyaltyController::class.'@coupon')->name('loyalty.coupon');
        });
    });
});

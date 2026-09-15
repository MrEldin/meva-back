<?php

use Meva\Api\V1\Controllers\Account\AccountController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Customer Account Routes
|--------------------------------------------------------------------------
|
| Registering, and following an order -- with an account or, for a guest, with
| the order number and the e-mail it was placed with.
|
*/

$api->version('v1', function ($api) {
    $api->group(['prefix' => 'account', 'as' => 'account'], function ($api) {
        $api->post('register', AccountController::class.'@register')->name('register');
        $api->post('track', AccountController::class.'@track')->name('track');

        $api->group(['middleware' => ['api', 'auth']], function ($api) {
            $api->get('orders', AccountController::class.'@orders')->name('orders');
        });
    });
});

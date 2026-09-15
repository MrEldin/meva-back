<?php

use Meva\Api\V1\Controllers\Admin\OrderController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Order Routes
|--------------------------------------------------------------------------
|
| The back office's order book. Reading is open to anyone who may see orders;
| changing a status needs the stronger permission.
|
*/

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth', 'permission:orders.view'],
        'prefix' => 'admin/orders',
        'as' => 'admin.orders',
    ], function ($api) {
        $api->get('', OrderController::class.'@index')->name('index');
        $api->get('summary', OrderController::class.'@summary')->name('summary');
        $api->get('export', OrderController::class.'@export')->name('export');
        $api->get('{id}', OrderController::class.'@show')->name('show');
    });

    $api->group([
        'middleware' => ['api', 'auth', 'permission:orders.manage'],
        'prefix' => 'admin/orders',
        'as' => 'admin.orders',
    ], function ($api) {
        $api->put('{id}/status', OrderController::class.'@updateStatus')->name('status');
    });
});

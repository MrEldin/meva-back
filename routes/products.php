<?php

use Meva\Api\V1\Controllers\Admin\ProductController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Catalogue Routes
|--------------------------------------------------------------------------
|
| Back-office catalogue management, consumed by the admin client. These routes
| are registered on Dingo's router and loaded from the withRouting(then: ...)
| callback in bootstrap/app.php.
|
*/

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api'],
        'prefix' => 'admin/products',
        'as' => 'admin.products',
    ], function ($api) {
        $api->get('', ProductController::class.'@index')->name('index');
        $api->post('', ProductController::class.'@create')->name('create');
        $api->get('{id}', ProductController::class.'@show')->name('show');
        $api->put('{id}', ProductController::class.'@update')->name('update');
        $api->delete('{id}', ProductController::class.'@destroy')->name('destroy');
    });
});

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth'],
        'prefix' => 'admin',
        'as' => 'admin',
    ], function ($api) {
        $api->get('analytics', \Meva\Api\V1\Controllers\Admin\AnalyticsController::class.'@index')->name('analytics');
    });
});

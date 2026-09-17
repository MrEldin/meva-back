<?php

use Meva\Api\V1\Controllers\Admin\ReviewController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Review Routes
|--------------------------------------------------------------------------
|
| Reading reviews is public and lives in routes/shop.php; everything that
| changes one needs the products permission, since a review belongs to a
| product and whoever looks after the catalogue looks after these too.
|
*/

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth', 'permission:products.manage'],
        'prefix' => 'admin/reviews',
        'as' => 'admin.reviews',
    ], function ($api) {
        $api->get('', ReviewController::class.'@index')->name('index');
        $api->get('proizvodi', ReviewController::class.'@products')->name('products');
        $api->post('', ReviewController::class.'@store')->name('store');
        $api->put('{id}', ReviewController::class.'@update')->name('update');
        $api->delete('{id}', ReviewController::class.'@destroy')->name('destroy');
    });
});

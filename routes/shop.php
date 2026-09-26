<?php

use Meva\Api\V1\Controllers\Shop\CatalogController;
use Meva\Api\V1\Controllers\Shop\CheckoutController;
use Meva\Api\V1\Controllers\Shop\SearchController;
use Meva\Api\V1\Controllers\Shop\SkinCheckController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Storefront Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated reads for the shop front. Registered on Dingo's
| router and loaded from bootstrap/app.php.
|
*/

$api->version('v1', function ($api) {
    $api->group(['prefix' => 'shop', 'as' => 'shop'], function ($api) {
        $api->get('products', CatalogController::class.'@index')->name('products.index');
        $api->get('products/{slug}', CatalogController::class.'@show')->name('products.show');
        $api->get('collections', CatalogController::class.'@collections')->name('collections');
        $api->get('reviews', CatalogController::class.'@reviews')->name('reviews');
        $api->get('search', SearchController::class)->name('search');
        $api->post('orders', CheckoutController::class.'@store')->name('orders.store');
        // The app's camera: a photograph in, a concern and a shelf out. Costs a model call, so it is rationed.
        $api->post('skin-check', ['middleware' => 'throttle:20,1', 'uses' => SkinCheckController::class.'@__invoke'])->name('skin-check');
    });
});

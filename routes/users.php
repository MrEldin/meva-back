<?php

use Meva\Api\V1\Controllers\UserController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are registered on Dingo's router. The file is loaded from the
| withRouting(then: ...) callback in bootstrap/app.php.
|
*/

use Meva\Api\V1\Controllers\Admin\TeamController;

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth', 'permission:users.manage'],
        'prefix' => 'admin/team',
        'as' => 'admin.team',
    ], function ($api) {
        $api->get('', TeamController::class.'@index')->name('index');
        $api->post('', TeamController::class.'@store')->name('store');
        $api->get('roles', TeamController::class.'@roles')->name('roles');
        $api->put('{id}', TeamController::class.'@update')->name('update');
        $api->delete('{id}', TeamController::class.'@destroy')->name('destroy');
    });
});

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth', 'permission:users.manage'],
        'prefix'     => 'users',
        'as'         => 'users'
    ], function ($api) {
        $api->post('', UserController::class . '@create')->name('create');
        $api->put('{id}', UserController::class . '@update')->name('update');
        $api->get('', UserController::class . '@index')->name('index');
        $api->get('{id}', UserController::class . '@show')->name('show');
    });
});

<?php

use Meva\Api\V1\Controllers\Admin\EmailCampaignController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| E-mail Campaign Routes
|--------------------------------------------------------------------------
|
| Writing and sending campaigns, all behind the marketing permission. The
| preview endpoint returns HTML rather than JSON: the editor drops it straight
| into an iframe, so what is on screen is the message itself.
|
*/

$api->version('v1', function ($api) {
    $api->group([
        'middleware' => ['api', 'auth', 'permission:marketing.manage'],
        'prefix' => 'admin/marketing/email',
        'as' => 'admin.email',
    ], function ($api) {
        $api->get('library', EmailCampaignController::class.'@library')->name('library');
        $api->get('campaigns', EmailCampaignController::class.'@index')->name('index');
        $api->post('campaigns', EmailCampaignController::class.'@store')->name('store');
        $api->get('campaigns/{campaign}', EmailCampaignController::class.'@show')->name('show');
        $api->put('campaigns/{campaign}', EmailCampaignController::class.'@update')->name('update');
        $api->delete('campaigns/{campaign}', EmailCampaignController::class.'@destroy')->name('destroy');
        $api->post('campaigns/{campaign}/preview', EmailCampaignController::class.'@preview')->name('preview');
        $api->get('campaigns/{campaign}/audience', EmailCampaignController::class.'@audience')->name('audience');
        $api->post('campaigns/{campaign}/test', EmailCampaignController::class.'@test')->name('test');
        $api->post('campaigns/{campaign}/send', EmailCampaignController::class.'@send')->name('send');
    });
});

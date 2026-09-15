<?php

use Meva\Api\V1\Controllers\Admin\MarketingController;

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Marketing Routes
|--------------------------------------------------------------------------
|
| Joining the mailing list is open to the storefront; everything that reads the
| list or the campaign figures needs the marketing permission.
|
*/

$api->version('v1', function ($api) {
    $api->post('newsletter', MarketingController::class.'@subscribe')->name('newsletter.subscribe');

    $api->group([
        'middleware' => ['api', 'auth', 'permission:marketing.manage'],
        'prefix' => 'admin/marketing',
        'as' => 'admin.marketing',
    ], function ($api) {
        $api->get('subscribers', MarketingController::class.'@subscribers')->name('subscribers');
        $api->get('subscribers/export', MarketingController::class.'@exportSubscribers')->name('subscribers.export');
        $api->get('campaigns', MarketingController::class.'@campaigns')->name('campaigns');
    });
});

<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application serves a JSON API through Dingo; see routes/api.php and the
| accompanying users.php, roles.php and permissions.php files.
|
| NOTE: this file previously declared an "/image/{sizeX}/{sizeY}/{file}" route
| pointing at an ImageController that has never existed in this repository. It
| resolved lazily through the old RouteServiceProvider's controller namespace,
| so the breakage only surfaced when the route was actually hit. Laravel no
| longer applies a controller namespace, so the dead route has been removed.
|
*/

use Meva\Web\Controllers\FeedController;
use Meva\Web\Controllers\ShareController;
use Meva\Web\Controllers\UnsubscribeController;

// Messengers, search engines and assistants do not run the storefront's
// JavaScript. nginx forwards their requests for these paths here, where the
// same pages are rendered server-side with proper tags and readable text.
Route::get('/proizvod/{slug}', [ShareController::class, 'product'])->name('share.product');
Route::get('/proizvodi', [ShareController::class, 'catalog'])->name('share.catalog');
Route::get('/sitemap.xml', [ShareController::class, 'sitemap'])->name('share.sitemap');
Route::get('/robots.txt', [ShareController::class, 'robots'])->name('share.robots');

// Leaving the mailing list. Signed rather than authenticated so one click is
// enough, and answered on POST too because Gmail and Yahoo send that
// themselves for one-click unsubscribe.
Route::get('/odjava', [UnsubscribeController::class, 'show'])->name('marketing.unsubscribe');
Route::post('/odjava', [UnsubscribeController::class, 'post'])->name('marketing.unsubscribe.post');

// The catalogue Meta and Google read for shopping adverts.
Route::get('/feed/proizvodi.xml', [FeedController::class, 'products'])->name('feed.products');

Route::get('/', [ShareController::class, 'home'])->name('share.home');

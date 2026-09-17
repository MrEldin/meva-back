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
Route::get('/product/{slug}', [ShareController::class, 'product'])->name('share.product');
Route::get('/products', [ShareController::class, 'catalog'])->name('share.catalog');
Route::get('/sitemap.xml', [ShareController::class, 'sitemap'])->name('share.sitemap');
Route::get('/robots.txt', [ShareController::class, 'robots'])->name('share.robots');

// The reading pages, so a link to one survives being shared and a search
// engine can read it.
Route::get('/{page}', [ShareController::class, 'page'])
    ->where('page', 'story|faq|delivery|returns')
    ->name('share.page');

// Leaving the mailing list. Signed rather than authenticated so one click is
// enough, and answered on POST too because Gmail and Yahoo send that
// themselves for one-click unsubscribe.
Route::get('/odjava', [UnsubscribeController::class, 'show'])->name('marketing.unsubscribe');
Route::post('/odjava', [UnsubscribeController::class, 'post'])->name('marketing.unsubscribe.post');

// The catalogue Meta and Google read for shopping adverts.
Route::get('/feed/products.xml', [FeedController::class, 'products'])->name('feed.products');

// The addresses these pages used to have. Google has them indexed and people
// have sent them to each other, so each one is answered with a permanent move
// to the English path rather than a 404.
foreach ([
    '/proizvodi' => '/products',
    '/prica' => '/story',
    '/cesta-pitanja' => '/faq',
    '/dostava' => '/delivery',
    '/reklamacije' => '/returns',
    '/feed/proizvodi.xml' => '/feed/products.xml',
] as $was => $now) {
    Route::get($was, fn () => redirect($now.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301));
}

Route::get('/proizvod/{slug}', fn (string $slug) => redirect('/product/'.$slug, 301));

Route::get('/', [ShareController::class, 'home'])->name('share.home');

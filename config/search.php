<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Meilisearch runs on the droplet itself, bound to localhost, so nothing
    | reaches it but this application. Without a host configured the shop
    | falls back to matching names in the database, which is what it did
    | before and is still better than an empty search box.
    |
    */

    'host' => env('MEILI_HOST', 'http://127.0.0.1:7700'),

    'key' => env('MEILI_KEY'),

    'enabled' => env('SEARCH_ENABLED', true),

    /*
    | How many of each kind come back in the drop-down. Enough to be useful,
    | few enough that the list does not become a page.
    */
    'limits' => [
        'products' => 6,
        'collections' => 4,
        'reviews' => 3,
        'pages' => 3,
    ],

];

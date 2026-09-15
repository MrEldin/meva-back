<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    |
    | Where the shop lives. Canonical links, share cards, the sitemap and the
    | advert feed all point at this address rather than at the API's own.
    |
    */

    'storefront_url' => env('STOREFRONT_URL', 'https://meva.life'),

    /*
    |--------------------------------------------------------------------------
    | Where the storefront's files live
    |--------------------------------------------------------------------------
    |
    | The built Vue app is uploaded to its own directory. Knowing the path lets
    | the share pages measure og-image.jpg from disk instead of fetching it back
    | through nginx.
    |
    */

    'storefront_root' => env('STOREFRONT_ROOT', '/var/www/meva-client/dist'),

    /*
    |--------------------------------------------------------------------------
    | Campaign send rate
    |--------------------------------------------------------------------------
    |
    | Messages per second. Resend meters sends and answers 429 above its limit,
    | so campaigns are paced rather than fired all at once. Two a second is the
    | free tier; raise it to match the plan in use.
    |
    */

    'mail_rate' => (int) env('MEVA_MAIL_RATE', 2),

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    |
    | The storefront asks for the catalogue on every page; it is held in the
    | cache until someone edits a product. Turn the cache off while working on
    | the transformers, so a change shows without clearing anything.
    |
    */

    'catalogue' => [
        'cache' => env('MEVA_CATALOGUE_CACHE', true),
        'ttl' => env('MEVA_CATALOGUE_TTL', 3600),
    ],


    /*
    |--------------------------------------------------------------------------
    | Catalogue Import
    |--------------------------------------------------------------------------
    |
    | Importing the exported photography copies 82 MB through the media library.
    | That is wanted when seeding a real environment and wasteful in a test
    | suite, so it can be switched off -- phpunit.xml disables it, and the tests
    | that cover the image path turn it back on for themselves.
    |
    */

    'catalogue' => [
        'import_images' => env('MEVA_IMPORT_IMAGES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Archive
    |--------------------------------------------------------------------------
    |
    | orders.json and customers.json carry names, addresses, phone numbers and
    | email addresses for around four thousand people. They are deliberately
    | kept outside the repository, so the archive seeder is pointed at wherever
    | the export actually lives instead of the files being copied in.
    |
    |     MEVA_ARCHIVE_PATH=/absolute/path/to/meva-assets/data
    |
    | The seeder is skipped when the path holds no export, so a checkout
    | without it still seeds cleanly.
    |
    */

    'archive' => [
        'path' => env('MEVA_ARCHIVE_PATH', storage_path('app/meva-export')),
    ],

];

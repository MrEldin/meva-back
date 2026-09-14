<?php

return [

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

];

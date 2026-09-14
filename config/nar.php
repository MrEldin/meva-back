<?php
/*
|--------------------------------------------------------------------------
| Nar Global Config
|--------------------------------------------------------------------------
|
|
*/
return [
    /*
    |--------------------------------------------------------------------------
    | Generator Config
    |--------------------------------------------------------------------------
    |
    */
    'generator' => [
        'basePath'           => base_path('/src'),
        'rootNamespace'      => 'Meva\\',
        'entitiesFolderName' => 'Entities',
        'stubsOverridePath'  => app_path('Console/Meva'),
        'paths'              => [
            'models'       => 'Models',
            'repositories' => 'Repositories',
            'contracts'    => 'Contracts',
            'transformers' => 'Transformers',
            'presenters'   => 'Presenters',
            'validators'   => 'Validators',
            'controllers'  => 'Controllers',
            'provider'     => 'Providers',
            'criteria'     => 'Criteria',
            'routes'       => 'Routes',
            'services'     => 'Services',
        ]
    ]
];

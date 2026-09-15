<?php

return [
    App\Providers\AppServiceProvider::class,
    Meva\AI\AiServiceProvider::class,
    Meva\Entities\Catalogue\Providers\CatalogueServiceProvider::class,
    Meva\Entities\Order\Providers\OrderServiceProvider::class,
    Meva\Entities\User\Providers\UserServiceProvider::class,
    Meva\Entities\Role\Providers\RoleServiceProvider::class,
    Meva\Entities\Permission\Providers\PermissionServiceProvider::class,
];

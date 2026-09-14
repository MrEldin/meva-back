<?php

namespace Meva\Entities\Permission\Providers;

use Illuminate\Support\ServiceProvider;
use Meva\Entities\Permission\Contracts\PermissionRepository;
use Meva\Entities\Permission\Repositories\PermissionRepositoryEloquent;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(PermissionRepository::class, PermissionRepositoryEloquent::class);
    }
}

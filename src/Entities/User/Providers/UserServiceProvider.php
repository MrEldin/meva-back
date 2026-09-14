<?php

namespace Meva\Entities\User\Providers;

use Illuminate\Support\ServiceProvider;
use Meva\Entities\User\Contracts\UserRepository;
use Meva\Entities\User\Repositories\UserRepositoryEloquent;

class UserServiceProvider extends ServiceProvider
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
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
    }
}

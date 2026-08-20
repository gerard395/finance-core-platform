<?php

namespace App\Providers;

use App\Application\Administration\AdministrationRepository;
use App\Application\Identity\UserRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(AdministrationRepository::class, EloquentAdministrationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

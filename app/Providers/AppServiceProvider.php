<?php

namespace App\Providers;

use App\Models\Carrera;
use App\Policies\CarreraPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Carrera::class, CarreraPolicy::class);
    }
}

<?php

namespace App\Providers;

use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers custom route model bindings.
 */
class RouteBindingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::bind('person', function ($value) {
            return app(PersonRepositoryInterface::class)->findById((int) $value);
        });

        Route::bind('trip', function ($value) {
            return app(TripRepositoryInterface::class)->findByIdOrFail((int) $value);
        });

        Route::bind('brand', function ($value) {
            return app(BrandRepositoryInterface::class)->findById((int) $value);
        });

        Route::bind('car', function ($value) {
            return app(CarRepositoryInterface::class)->findOrFail((int) $value);
        });
    }
}

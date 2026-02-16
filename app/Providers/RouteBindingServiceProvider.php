<?php

namespace App\Providers;

use App\Models\Person;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

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
        // persons/{person} => eager load what your policies/resources use
        Route::bind('person', function ($value) {
            $id = (int) $value;

            return Cache::tags(['persons'])->remember(
                "persons:id:{$id}",
                now()->addMinutes(10),
                function () use ($id) {
                    // keep it light; load relations later if needed
                    return Person::query()->findOrFail($id);
                }
            );
        });

        // trips/{trip} => same idea
        Route::bind('trip', function ($value) {
            return Trip::query()
                ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                ->findOrFail((int) $value);
        });

    }
}

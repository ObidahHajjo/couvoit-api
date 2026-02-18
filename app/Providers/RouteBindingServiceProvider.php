<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Car;
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
        /**
         * PERSON binding: /persons/{person} + related routes.
         */
        Route::bind('person', function ($value) {
            $id = (int) $value;

            return Cache::tags(['persons'])->remember(
                "persons:id:{$id}",
                now()->addMinutes(10),
                fn () => Person::query()->findOrFail($id)
            );
        });

        /**
         * TRIP binding: /trips/{trip} + related routes.
         */
        Route::bind('trip', function ($value) {
            $id = (int) $value;

            return Cache::tags(['trips'])->remember(
                "trips:id:{$id}",
                now()->addMinutes(10),
                fn () => Trip::query()->findOrFail($id)
            );
        });

        /**
         * BRAND binding: /brand/{brand}
         * (Your route is singular: /brand/{brand}. The param name is still {brand}.)
         */
        Route::bind('brand', function ($value) {
            $id = (int) $value;

            return Cache::tags(['brands'])->remember(
                "brands:id:{$id}",
                now()->addMinutes(10),
                fn () => Brand::query()->findOrFail($id)
            );
        });

        /**
         * CAR binding: /cars/{car}
         */
        Route::bind('car', function ($value) {
            $id = (int) $value;

            return Cache::tags(['cars'])->remember(
                "cars:id:{$id}",
                now()->addMinutes(10),
                fn () => Car::query()->findOrFail($id)
            );
        });
    }
}

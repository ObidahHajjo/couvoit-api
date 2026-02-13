<?php

namespace App\Providers;

use App\Repositories\Eloquent\AddressEloquentRepository;
use App\Repositories\Eloquent\BrandEloquentRepository;
use App\Repositories\Eloquent\CarModelEloquentRepository;
use App\Repositories\Eloquent\CarRepositoryEloquent;
use App\Repositories\Eloquent\CityEloquentRepository;
use App\Repositories\Eloquent\ColorEloquentRepository;
use App\Repositories\Eloquent\PersonEloquentRepository;
use App\Repositories\Eloquent\ReservationEloquentRepository;
use App\Repositories\Eloquent\TripEloquentRepository;
use App\Repositories\Eloquent\TypeEloquentRepository;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\CityRepositoryInterface;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            AddressRepositoryInterface::class,
            AddressEloquentRepository::class
        );

        $this->app->bind(
            BrandRepositoryInterface::class,
            BrandEloquentRepository::class
        );

        $this->app->bind(
            CarModelRepositoryInterface::class,
            CarModelEloquentRepository::class
        );

        $this->app->bind(
            CarRepositoryInterface::class,
            CarRepositoryEloquent::class
        );

        $this->app->bind(
            CityRepositoryInterface::class,
            CityEloquentRepository::class
        );

        $this->app->bind(
            ColorRepositoryInterface::class,
            ColorEloquentRepository::class
        );

        $this->app->bind(
            PersonRepositoryInterface::class,
            PersonEloquentRepository::class
        );

        $this->app->bind(
            ReservationRepositoryInterface::class,
            ReservationEloquentRepository::class
        );

        $this->app->bind(
            TripRepositoryInterface::class,
            TripEloquentRepository::class
        );

        $this->app->bind(
            TypeRepositoryInterface::class,
            TypeEloquentRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace Tests\Unit\Providers;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Person;
use App\Models\Trip;
use App\Providers\RouteBindingServiceProvider;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class RouteBindingServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_person_binding_uses_person_repository_cache_pipeline(): void
    {
        $person = new Person(['id' => 12]);
        $repo = Mockery::mock(PersonRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->with(12)->andReturn($person);

        $this->app->instance(PersonRepositoryInterface::class, $repo);

        (new RouteBindingServiceProvider($this->app))->boot();

        $binding = Route::getBindingCallback('person');

        self::assertSame($person, $binding('12'));
    }

    public function test_trip_binding_uses_trip_repository_cache_pipeline(): void
    {
        $trip = new Trip(['id' => 21]);
        $repo = Mockery::mock(TripRepositoryInterface::class);
        $repo->shouldReceive('findByIdOrFail')->once()->with(21)->andReturn($trip);

        $this->app->instance(TripRepositoryInterface::class, $repo);

        (new RouteBindingServiceProvider($this->app))->boot();

        $binding = Route::getBindingCallback('trip');

        self::assertSame($trip, $binding('21'));
    }

    public function test_brand_binding_uses_brand_repository_cache_pipeline(): void
    {
        $brand = new Brand(['id' => 7]);
        $repo = Mockery::mock(BrandRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->with(7)->andReturn($brand);

        $this->app->instance(BrandRepositoryInterface::class, $repo);

        (new RouteBindingServiceProvider($this->app))->boot();

        $binding = Route::getBindingCallback('brand');

        self::assertSame($brand, $binding('7'));
    }

    public function test_car_binding_uses_car_repository_cache_pipeline(): void
    {
        $car = new Car(['id' => 5]);
        $repo = Mockery::mock(CarRepositoryInterface::class);
        $repo->shouldReceive('findOrFail')->once()->with(5)->andReturn($car);

        $this->app->instance(CarRepositoryInterface::class, $repo);

        (new RouteBindingServiceProvider($this->app))->boot();

        $binding = Route::getBindingCallback('car');

        self::assertSame($car, $binding('5'));
    }
}

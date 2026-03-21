<?php

namespace Tests\Unit\Repositories;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Type;
use App\Repositories\Eloquent\CarRepositoryEloquent;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CarRepositoryEloquentTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_returns_collection_and_warms_per_car_cache(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type = Type::query()->create(['type' => 'suv']);

        $model = CarModel::query()->create([
            'name' => 'rav4',
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);

        $c1 = Car::query()->create(['license_plate' => 'AA-1', 'seats' => 5, 'model_id' => $model->id, 'color_id' => $color->id]);
        $c2 = Car::query()->create(['license_plate' => 'AA-2', 'seats' => 5, 'model_id' => $model->id, 'color_id' => $color->id]);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new CarRepositoryEloquent($cache);

        $collection = collect([$c1, $c2]);

        $cache->shouldReceive('rememberCarsAll')
            ->once()
            ->andReturnUsing(function ($callback) use ($collection) {
                return $collection;
            });

        $cache->shouldReceive('putCar')
            ->once()
            ->with(Mockery::type(Car::class));

        $cache->shouldReceive('putCar')
            ->once()
            ->with(Mockery::type(Car::class));

        $res = $repo->all();

        $this->assertCount(2, $res);
    }

    public function test_find_or_fail_returns_car(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type = Type::query()->create(['type' => 'suv']);
        $model = CarModel::query()->create(['name' => 'rav4', 'brand_id' => $brand->id, 'type_id' => $type->id]);
        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);
        $car = Car::query()->create(['license_plate' => 'AA-1', 'seats' => 5, 'model_id' => $model->id, 'color_id' => $color->id]);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new CarRepositoryEloquent($cache);

        $cache->shouldReceive('rememberCarById')
            ->once()
            ->with($car->id, Mockery::type('callable'))
            ->andReturnUsing(function ($id, $callback) {
                return $callback();
            });

        $res = $repo->findOrFail($car->id);

        $this->assertSame($car->id, $res->id);
    }
}

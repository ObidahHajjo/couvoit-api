<?php

namespace Tests\Unit\Repositories;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Type;
use App\Repositories\Eloquent\CarRepositoryEloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Throwable;

/**
 * Class CarRepositoryEloquentTest
 *
 * Unit-style tests for CarRepositoryEloquent using Cache facade mocks
 * because cache tags are not supported on all cache stores in tests.
 */
class CarRepositoryEloquentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Repository under test.
     *
     * @var CarRepositoryEloquent
     */
    private CarRepositoryEloquent $repo;

    /**
     * Setup repository.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CarRepositoryEloquent();
    }

    /**
     * all() should return cars and warm per-car caches.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_all_returns_collection_and_warms_per_car_cache(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type  = Type::query()->create(['type' => 'suv']);

        $model = CarModel::query()->create([
            'name' => 'rav4',
            'seats' => 5,
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);

        $c1 = Car::query()->create(['license_plate' => 'AA-1', 'model_id' => $model->id, 'color_id' => $color->id]);
        $c2 = Car::query()->create(['license_plate' => 'AA-2', 'model_id' => $model->id, 'color_id' => $color->id]);

        $carsTag = Mockery::mock();
        $carTag1 = Mockery::mock();
        $carTag2 = Mockery::mock();

        Cache::shouldReceive('tags')->with(['cars'])->andReturn($carsTag);

        $carsTag->shouldReceive('remember')
            ->once()
            ->with('cars:all', Mockery::type('int'), Mockery::type('callable'))
            ->andReturnUsing(fn($k, $t, $cb) => $cb());

        Cache::shouldReceive('tags')->with(['cars', 'car:' . $c1->id])->andReturn($carTag1);
        Cache::shouldReceive('tags')->with(['cars', 'car:' . $c2->id])->andReturn($carTag2);

        $carTag1->shouldReceive('put')->once()->with('cars:' . $c1->id, Mockery::type(Car::class), Mockery::type('int'));
        $carTag2->shouldReceive('put')->once()->with('cars:' . $c2->id, Mockery::type(Car::class), Mockery::type('int'));

        $res = $this->repo->all();

        $this->assertCount(2, $res);
    }

    /**
     * findOrFail() should return a car via remember closure.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_find_or_fail_returns_car(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type  = Type::query()->create(['type' => 'suv']);
        $model = CarModel::query()->create(['name' => 'rav4', 'seats' => 5, 'brand_id' => $brand->id, 'type_id' => $type->id]);
        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);
        $car = Car::query()->create(['license_plate' => 'AA-1', 'model_id' => $model->id, 'color_id' => $color->id]);

        $tag = Mockery::mock();
        Cache::shouldReceive('tags')->with(['cars', 'car:' . $car->id])->andReturn($tag);

        $tag->shouldReceive('remember')
            ->once()
            ->with('cars:' . $car->id, Mockery::type('int'), Mockery::type('callable'))
            ->andReturnUsing(fn($k, $t, $cb) => $cb());

        $res = $this->repo->findOrFail($car->id);

        $this->assertSame($car->id, $res->id);
    }
}

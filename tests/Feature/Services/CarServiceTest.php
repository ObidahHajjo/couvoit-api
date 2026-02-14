<?php

namespace Tests\Unit\Services;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Type;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use App\Services\Implementations\CarService;
use Mockery;
use Tests\TestCase;

class CarServiceTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_car_calls_repos_and_returns_car(): void
    {
        $brandRepo = Mockery::mock(BrandRepositoryInterface::class);
        $typeRepo  = Mockery::mock(TypeRepositoryInterface::class);
        $modelRepo = Mockery::mock(CarModelRepositoryInterface::class);
        $colorRepo = Mockery::mock(ColorRepositoryInterface::class);
        $carRepo   = Mockery::mock(CarRepositoryInterface::class);

        $brand = new Brand(['id' => 1, 'name' => 'toyota']);
        $type  = new Type(['id' => 2, 'type' => 'sedan']);
        $model = new CarModel(['id' => 3, 'name' => 'corolla', 'brand_id' => 1, 'type_id' => 2, 'seats' => 5]);
        $color = new Color(['id' => 4, 'hex_code' => '#ffffff']);
        $car   = new Car(['id' => 10, 'license_plate' => 'AA-123-AA', 'model_id' => 3, 'color_id' => 4]);

        $brandRepo->shouldReceive('createOrFirst')->once()->andReturn($brand);
        $typeRepo->shouldReceive('createOrFirst')->once()->andReturn($type);
        $modelRepo->shouldReceive('createOrFirst')->once()->andReturn($model);
        $colorRepo->shouldReceive('createOrFirst')->once()->andReturn($color);

        $carRepo->shouldReceive('createCar')->once()->andReturn($car);

        $service = new CarService($carRepo, $brandRepo, $modelRepo, $typeRepo, $colorRepo);

        $result = $service->createCar([
            'license_plate' => 'AA-123-AA',
            'brand' => ['name' => 'toyota'],
            'type' => ['name' => 'sedan'],
            'model' => ['name' => 'corolla', 'seats' => 5],
            'color' => ['hex_code' => '#ffffff'],
        ]);

        $this->assertInstanceOf(Car::class, $result);
        $this->assertEquals('AA-123-AA', $result->license_plate);
    }
}

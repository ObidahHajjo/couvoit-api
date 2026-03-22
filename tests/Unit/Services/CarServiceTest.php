<?php

namespace Tests\Unit\Services;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\DTOS\Car\ResolvedCarRefs;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;
use App\Models\Person;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Implementations\CarService;
use App\Support\Cache\RepositoryCacheManager;
use App\Support\Car\CarCatalogNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CarServiceTest extends TestCase
{
    private CarRepositoryInterface $cars;

    private CarReferenceResolverInterface $resolver;

    private PersonRepositoryInterface $persons;

    private CarModelRepositoryInterface $models;

    private CarCatalogNormalizer $normalizer;

    private RepositoryCacheManager $cache;

    private CarService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cars = Mockery::mock(CarRepositoryInterface::class);
        $this->resolver = Mockery::mock(CarReferenceResolverInterface::class);
        $this->persons = Mockery::mock(PersonRepositoryInterface::class);
        $this->models = Mockery::mock(CarModelRepositoryInterface::class);
        $this->normalizer = new CarCatalogNormalizer;
        $this->cache = Mockery::mock(RepositoryCacheManager::class);

        $this->service = new CarService(
            $this->cars,
            $this->resolver,
            $this->persons,
            $this->models,
            $this->normalizer,
            $this->cache
        );

        DB::shouldReceive('transaction')->andReturnUsing(static fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_cars_delegates_to_repository(): void
    {
        $collection = new Collection([new Car]);
        $this->cars->shouldReceive('all')->once()->andReturn($collection);

        $res = $this->service->getCars();

        $this->assertSame($collection, $res);
    }

    public function test_create_car_throws_when_user_already_has_car(): void
    {
        $person = new Person(['car_id' => 10]);

        $dto = new CarCreateData(
            licensePlate: 'AA-123-BB',
            modelName: 'golf',
            seats: 5,
            brandName: 'vw',
            typeName: 'hatch',
            colorHex: '#00aaff',
            colorName: 'sky',
        );

        $this->expectException(ConflictException::class);

        $this->service->createCar($dto, $person);
    }

    public function test_create_car_happy_path(): void
    {
        $person = new Person(['car_id' => null]);
        $person->id = 5;

        $dto = new CarCreateData(
            licensePlate: 'AA-123-BB',
            modelName: 'golf',
            seats: 5,
            brandName: 'vw',
            typeName: 'hatch',
            colorHex: '#00aaff',
            colorName: 'sky',
        );

        $refs = new ResolvedCarRefs(
            brandId: 0,
            typeId: 0,
            modelId: 2,
            colorId: 1
        );

        $this->resolver->shouldReceive('resolveForCreate')
            ->once()
            ->andReturn($refs);

        $created = new Car;
        $created->id = 99;

        $fresh = new Car;
        $fresh->id = 99;

        $this->cars->shouldReceive('create')
            ->once()
            ->with([
                'color_id' => 1,
                'model_id' => 2,
                'license_plate' => 'AA-123-BB',
                'seats' => 5,
            ])
            ->andReturn($created);

        $this->persons->shouldReceive('attachCar')
            ->once()
            ->with($person, 99)
            ->andReturnTrue();

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(99)
            ->andReturn($fresh);

        $res = $this->service->createCar($dto, $person);

        $this->assertSame(99, $res->id);
    }

    public function test_update_car_throws_when_empty(): void
    {
        $car = new Car;
        $dto = new CarUpdateData;

        $this->expectException(ValidationLogicException::class);

        $this->service->updateCar($car, $dto);
    }

    public function test_update_car_updates_license_plate(): void
    {
        $car = new Car;
        $car->id = 10;

        $dto = new CarUpdateData(licensePlate: 'AB-999-CD');

        $this->cars->shouldReceive('update')
            ->once()
            ->with($car, ['license_plate' => 'AB-999-CD'])
            ->andReturnTrue();

        $fresh = new Car;
        $fresh->id = 10;

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(10)
            ->andReturn($fresh);

        $res = $this->service->updateCar($car, $dto);

        $this->assertSame(10, $res->id);
    }

    public function test_update_car_updates_model_via_resolver(): void
    {
        $car = new Car;
        $car->id = 10;

        $dto = new CarUpdateData(
            modelName: 'golf',
            brandName: 'vw',
            typeName: 'hatch',
        );

        $this->resolver->shouldReceive('resolveModelForUpdate')
            ->once()
            ->with($car, Mockery::type('array'))
            ->andReturn(123);

        $this->cars->shouldReceive('update')
            ->once()
            ->with($car, ['model_id' => 123])
            ->andReturnTrue();

        $fresh = new Car;
        $fresh->id = 10;

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(10)
            ->andReturn($fresh);

        $res = $this->service->updateCar($car, $dto);

        $this->assertSame(10, $res->id);
    }

    public function test_update_car_updates_seats(): void
    {
        $car = new Car;
        $car->id = 10;

        $dto = new CarUpdateData(seats: 6);

        $this->cars->shouldReceive('update')
            ->once()
            ->with($car, ['seats' => 6])
            ->andReturnTrue();

        $fresh = new Car;
        $fresh->id = 10;

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(10)
            ->andReturn($fresh);

        $res = $this->service->updateCar($car, $dto);

        $this->assertSame(10, $res->id);
    }
}

<?php

namespace Tests\Unit\Services;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Exceptions\ConflictException;
use App\Exceptions\InactiveUserException;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;
use App\Models\Person;
use App\Models\User;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Implementations\CarService;
use Illuminate\Support\Collection;
use App\DTOS\Car\ResolvedCarRefs;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

/**
 * Class CarServiceTest
 *
 * Unit tests for CarService business rules and delegation to dependencies.
 */
class CarServiceTest extends TestCase
{
    /**
     * Mocked CarRepositoryInterface dependency.
     *
     * @var CarRepositoryInterface&MockInterface
     */
    private CarRepositoryInterface $cars;

    /**
     * Mocked CarReferenceResolverInterface dependency.
     *
     * @var CarReferenceResolverInterface&MockInterface
     */
    private CarReferenceResolverInterface $resolver;

    /**
     * Mocked PersonRepositoryInterface dependency.
     *
     * @var PersonRepositoryInterface&MockInterface
     */
    private PersonRepositoryInterface $persons;

    /**
     * Service under test.
     *
     * @var CarService
     */
    private CarService $service;

    /**
     * Setup mocks and service instance.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cars = Mockery::mock(CarRepositoryInterface::class);
        $this->resolver = Mockery::mock(CarReferenceResolverInterface::class);
        $this->persons = Mockery::mock(PersonRepositoryInterface::class);

        $this->service = new CarService($this->cars, $this->resolver, $this->persons);
    }

    /**
     * getCars() delegates to repository->all().
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_get_cars_delegates_to_repository(): void
    {
        $collection = new Collection([new Car()]);
        $this->cars->shouldReceive('all')->once()->andReturn($collection);

        $res = $this->service->getCars();

        $this->assertSame($collection, $res);
    }

    /**
     * createCar() throws when user already has a car.
     *
     * @return void
     *
     * @throws Throwable
     */
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

    /**
     * createCar() happy path creates car, attaches to person, returns fresh loaded car.
     *
     * @return void
     *
     * @throws Throwable
     */
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

        // Resolver returns a refs object (whatever your real resolver returns)
        $refs = new ResolvedCarRefs(
            brandId: 0,
            typeId: 0,
            modelId: 2,
            colorId: 1
        );

        $this->resolver->shouldReceive('resolveForCreate')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($dto) {
                return $payload['brand']['name'] === $dto->brandName
                    && $payload['type']['name'] === $dto->typeName
                    && $payload['model']['name'] === $dto->modelName
                    && $payload['model']['seats'] === $dto->seats
                    && $payload['color']['hex_code'] === $dto->colorHex;
            }))
            ->andReturn($refs);

        $created = new Car();
        $created->id = 99;

        $fresh = new Car();
        $fresh->id = 99;

        $this->cars->shouldReceive('create')
            ->once()
            ->with([
                'color_id' => 1,
                'model_id' => 2,
                'license_plate' => 'AA-123-BB',
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

    /**
     * updateCar() throws when DTO is empty.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_update_car_throws_when_empty(): void
    {
        $car = new Car();
        $dto = new CarUpdateData();

        $this->expectException(ValidationLogicException::class);

        $this->service->updateCar($car, $dto);
    }

    /**
     * updateCar() updates license plate and returns fresh car.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_update_car_updates_license_plate(): void
    {
        $car = new Car();
        $car->id = 10;

        $dto = new CarUpdateData(licensePlate: 'AB-999-CD');

        $this->cars->shouldReceive('update')
            ->once()
            ->with($car, ['license_plate' => 'AB-999-CD'])
            ->andReturnTrue();

        $fresh = new Car();
        $fresh->id = 10;

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(10)
            ->andReturn($fresh);

        $res = $this->service->updateCar($car, $dto);

        $this->assertSame(10, $res->id);
    }

    /**
     * updateCar() updates model when modelName is provided (via resolver).
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_update_car_updates_model_via_resolver(): void
    {
        $car = new Car();
        $car->id = 10;

        $dto = new CarUpdateData(
            modelName: 'golf',
            seats: 5,
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

        $fresh = new Car();
        $fresh->id = 10;

        $this->cars->shouldReceive('findOrFail')
            ->once()
            ->with(10)
            ->andReturn($fresh);

        $res = $this->service->updateCar($car, $dto);

        $this->assertSame(10, $res->id);
    }

    /**
     * deleteCar() delegates to repository->delete().
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_delete_car_delegates(): void
    {
        $car = new Car();

        $this->cars->shouldReceive('delete')
            ->once()
            ->with($car)
            ->andReturnNull();

        $this->service->deleteCar($car);

        $this->assertTrue(true);
    }
}

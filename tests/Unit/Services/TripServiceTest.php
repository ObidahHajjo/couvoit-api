<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationLogicException;
use App\Models\Address;
use App\Models\City;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Resolvers\Interfaces\AddressResolverInterface;
use App\Services\Implementations\TripService;
use App\Services\Interfaces\OrsRoutingClientInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

/**
 * Class TripServiceTest
 *
 * Unit tests for TripService business rules and delegation.
 */
final class TripServiceTest extends TestCase
{
    /**
     * @var TripRepositoryInterface&MockInterface
     */
    private TripRepositoryInterface $trips;

    /**
     * @var AddressResolverInterface&MockInterface
     */
    private AddressResolverInterface $resolver;

    /**
     * @var AddressRepositoryInterface&MockInterface
     */
    private AddressRepositoryInterface $addresses;

    /**
     * @var OrsRoutingClientInterface&MockInterface
     */
    private OrsRoutingClientInterface $ors;

    /**
     * @var TripService
     */
    private TripService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-18 12:00:00'));

        $this->trips = Mockery::mock(TripRepositoryInterface::class);
        $persons = Mockery::mock(PersonRepositoryInterface::class);
        $this->resolver = Mockery::mock(AddressResolverInterface::class);
        $this->addresses = Mockery::mock(AddressRepositoryInterface::class);
        $this->ors = Mockery::mock(OrsRoutingClientInterface::class);

        $this->service = new TripService(
            $this->trips,
            $persons,
            $this->resolver,
            $this->addresses,
            $this->ors
        );

        // Make DB::transaction run the callback directly (unit test style)
        DB::shouldReceive('transaction')
            ->andReturnUsing(static fn(callable $cb) => $cb());

        // Avoid real cache
        Cache::shouldReceive('remember')->andReturnUsing(static fn($k, $ttl, callable $cb) => $cb());
        Cache::shouldReceive('forget')->byDefault();
        Cache::shouldReceive('add')->byDefault();
        Cache::shouldReceive('increment')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    public function test_create_trip_for_another_user_forbidden_if_not_admin(): void
    {
        $this->expectException(ForbiddenException::class);

        $auth = new Person();
        $auth->id = 1;
        $auth->role_id = Person::ROLE_USER;

        $payload = [
            'person_id' => 2,
            'trip_datetime' => '2026-02-20 10:00:00',
            'kms' => 10,
            'available_seats' => 2,
            'starting_address' => ['street' => 'A'],
            'arrival_address' => ['street' => 'B'],
        ];

        $this->service->createTrip($payload, $auth);
    }

    /**
     * @throws Throwable
     */
    public function test_create_trip_requires_driver_has_car(): void
    {
        $this->expectException(ForbiddenException::class);

        $auth = new Person();
        $auth->id = 1;
        $auth->role_id = Person::ROLE_USER;
        $auth->car_id = null;

        $payload = [
            'trip_datetime' => '2026-02-20 10:00:00',
            'kms' => 10,
            'available_seats' => 2,
            'starting_address' => ['street' => 'A'],
            'arrival_address' => ['street' => 'B'],
        ];

        $this->service->createTrip($payload, $auth);
    }

    /**
     * @throws Throwable
     */
    public function test_create_trip_happy_path_creates_trip_and_returns_fresh_trip(): void
    {
        $auth = new Person();
        $auth->id = 10;
        $auth->role_id = Person::ROLE_USER;
        $auth->car_id = 5;

        $payload = [
            'trip_datetime' => '2026-02-20 10:00:00',
            'kms' => 120,
            'available_seats' => 3,
            'smoking_allowed' => true,
            'starting_address' => ['street' => 'Dep'],
            'arrival_address' => ['street' => 'Arr'],
        ];

        $depId = 111;
        $arrId = 222;

        $this->resolver->shouldReceive('resolveId')->once()->with($payload['starting_address'])->andReturn($depId);
        $this->resolver->shouldReceive('resolveId')->once()->with($payload['arrival_address'])->andReturn($arrId);

        $dep = new Address();
        $dep->id = $depId;
        $dep->street_number = '1';
        $dep->street = 'Dep';
        $dep->setRelation('city', (new City(['postal_code'=>'75000','name'=>'Paris'])));

        $arr = new Address();
        $arr->id = $arrId;
        $arr->street_number = '2';
        $arr->street = 'Arr';
        $arr->setRelation('city', (object) ['postal_code' => '69000', 'name' => 'Lyon']);

        $this->addresses->shouldReceive('findOrFail')->once()->with($depId)->andReturn($dep);
        $this->addresses->shouldReceive('findOrFail')->once()->with($arrId)->andReturn($arr);

        $this->ors->shouldReceive('geocode')->twice()->andReturn(['lng' => 2.0, 'lat' => 48.0]);
        $this->ors->shouldReceive('durationSeconds')->once()->andReturn(3600);

        $created = new Trip();
        $created->id = 999;

        $this->trips->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use ($auth, $depId, $arrId): bool {
                return (int)$attrs['person_id'] === $auth->id
                    && (int)$attrs['departure_address_id'] === $depId
                    && (int)$attrs['arrival_address_id'] === $arrId
                    && isset($attrs['arrival_time']);
            }))
            ->andReturn($created);

        $fresh = new Trip();
        $fresh->id = 999;

        $this->trips->shouldReceive('findByIdOrFail')->once()->with(999)->andReturn($fresh);

        $res = $this->service->createTrip($payload, $auth);

        self::assertSame(999, $res->id);
    }

    /**
     * @throws Throwable
     */
    public function test_update_trip_throws_when_nothing_to_update(): void
    {
        $this->expectException(ValidationLogicException::class);

        $trip = new Trip();
        $trip->id = 1;

        $this->service->updateTrip($trip, [], new Person());
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_seat_driver_cannot_reserve_own_trip(): void
    {
        $this->expectException(ValidationLogicException::class);

        $trip = new Trip();
        $trip->id = 1;
        $trip->person_id = 10;
        $trip->departure_time = Carbon::parse('2026-02-20 10:00:00');

        $auth = new Person();
        $auth->id = 10;
        $auth->role_id = Person::ROLE_USER;

        $this->service->reserveSeat($trip, 10, $auth);
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_seat_throws_when_already_reserved(): void
    {
        $this->expectException(\App\Exceptions\ConflictException::class);

        $trip = new \App\Models\Trip();
        $trip->id = 1;
        $trip->person_id = 99;
        $trip->departure_time = \Carbon\Carbon::parse('2026-02-20 10:00:00');
        $trip->available_seats = 3;

        $auth = new \App\Models\Person();
        $auth->id = 10;
        $auth->role_id = \App\Models\Person::ROLE_USER;

        $relation = \Mockery::mock(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        $relation->shouldReceive('wherePivot')->once()->with('person_id', 10)->andReturnSelf();
        $relation->shouldReceive('exists')->once()->andReturnTrue();

        $locked = new class extends \App\Models\Trip {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|null */
            public ?\Illuminate\Database\Eloquent\Relations\BelongsToMany $passengersRelation = null;

            public function passengers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
            {
                /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany */
                $rel = $this->passengersRelation;

                return $rel;
            }
        };

        $locked->id = 1;
        $locked->available_seats = 3;
        $locked->passengersRelation = $relation;

        $this->trips->shouldReceive('findByIdForUpdate')->once()->with(1)->andReturn($locked);

        $this->service->reserveSeat($trip, 10, $auth);
    }
}

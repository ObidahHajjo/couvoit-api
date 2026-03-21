<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ForbiddenException;
use App\Models\Address;
use App\Models\Car;
use App\Models\City;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Resolvers\Interfaces\AddressResolverInterface;
use App\Services\Implementations\TripService;
use App\Services\Interfaces\OrsRoutingClientInterface;
use App\Services\Interfaces\TripEmailServiceInterface;
use App\Support\Cache\RepositoryCacheManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class TripServiceTest extends TestCase
{
    private TripRepositoryInterface $trips;

    private PersonRepositoryInterface $persons;

    private AddressResolverInterface $resolver;

    private AddressRepositoryInterface $addresses;

    private OrsRoutingClientInterface $ors;

    private RepositoryCacheManager $cache;

    private TripEmailServiceInterface $tripEmails;

    private TripService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-18 12:00:00'));

        $this->trips = Mockery::mock(TripRepositoryInterface::class);
        $this->persons = Mockery::mock(PersonRepositoryInterface::class);
        $this->resolver = Mockery::mock(AddressResolverInterface::class);
        $this->addresses = Mockery::mock(AddressRepositoryInterface::class);
        $this->ors = Mockery::mock(OrsRoutingClientInterface::class);
        $this->cache = Mockery::mock(RepositoryCacheManager::class);
        $this->tripEmails = Mockery::mock(TripEmailServiceInterface::class);

        $this->service = new TripService(
            $this->trips,
            $this->persons,
            $this->resolver,
            $this->addresses,
            $this->ors,
            $this->cache,
            $this->tripEmails,
        );

        DB::shouldReceive('transaction')->andReturnUsing(static fn (callable $callback) => $callback());
        DB::shouldReceive('afterCommit')->andReturnUsing(static fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_trip_for_another_user_forbidden_if_not_admin(): void
    {
        $this->expectException(ForbiddenException::class);

        $auth = $this->makePerson(1, 'auth@example.test');
        $car = new Car;
        $car->seats = 4;
        $auth->setRelation('car', $car);

        $payload = [
            'person_id' => 2,
            'trip_datetime' => '2026-02-20 10:00:00',
            'available_seats' => 2,
            'starting_address' => ['street' => 'A'],
            'arrival_address' => ['street' => 'B'],
        ];

        $this->service->createTrip($payload, $auth);
    }

    public function test_reserve_seat_invalidates_cache_and_sends_emails_after_commit(): void
    {
        $driver = $this->makePerson(99, 'driver@example.test', 'Driver');
        $passenger = $this->makePerson(10, 'passenger@example.test', 'Passenger');

        $trip = $this->makeTrip(1, $driver, collect());

        $relation = Mockery::mock(BelongsToMany::class);
        $relation->shouldReceive('wherePivot')->once()->with('person_id', 10)->andReturnSelf();
        $relation->shouldReceive('exists')->once()->andReturnFalse();
        $relation->shouldReceive('count')->once()->andReturn(0);
        $relation->shouldReceive('attach')->once()->with(10);

        $lockedTrip = new class extends Trip
        {
            public BelongsToMany $passengersRelation;

            public function passengers(): BelongsToMany
            {
                return $this->passengersRelation;
            }
        };

        $lockedTrip->id = 1;
        $lockedTrip->person_id = 99;
        $lockedTrip->available_seats = 3;
        $lockedTrip->departure_time = Carbon::parse('2026-02-20 10:00:00');
        $lockedTrip->arrival_time = Carbon::parse('2026-02-20 12:00:00');
        $lockedTrip->passengersRelation = $relation;
        $this->attachTripRelations($lockedTrip, $driver, collect());

        $this->trips->shouldReceive('findByIdForUpdate')->once()->with(1)->andReturn($lockedTrip);
        $this->persons->shouldReceive('findById')->once()->with(10)->andReturn($passenger);
        $this->cache->shouldReceive('invalidateReservationWrite')->once()->with(1, 10, 99);
        $this->tripEmails->shouldReceive('sendReservationCreated')->once()->with($lockedTrip, $passenger);

        self::assertTrue($this->service->reserveSeat($trip, 10, $passenger));
    }

    public function test_cancel_reservation_invalidates_cache_and_sends_emails_after_commit(): void
    {
        $driver = $this->makePerson(99, 'driver@example.test', 'Driver');
        $passenger = $this->makePerson(10, 'passenger@example.test', 'Passenger');

        $trip = $this->makeTrip(1, $driver, collect([$passenger]));

        $relation = Mockery::mock(BelongsToMany::class);
        $relation->shouldReceive('detach')->once()->with(10)->andReturn(1);

        $lockedTrip = new class extends Trip
        {
            public BelongsToMany $passengersRelation;

            public function passengers(): BelongsToMany
            {
                return $this->passengersRelation;
            }
        };

        $lockedTrip->id = 1;
        $lockedTrip->person_id = 99;
        $lockedTrip->departure_time = Carbon::parse('2026-02-20 10:00:00');
        $lockedTrip->arrival_time = Carbon::parse('2026-02-20 12:00:00');
        $lockedTrip->passengersRelation = $relation;
        $this->attachTripRelations($lockedTrip, $driver, collect([$passenger]));

        $this->trips->shouldReceive('findByIdForUpdate')->once()->with(1)->andReturn($lockedTrip);
        $this->persons->shouldReceive('findById')->once()->with(10)->andReturn($passenger);
        $this->cache->shouldReceive('invalidateReservationWrite')->once()->with(1, 10, 99);
        $this->tripEmails->shouldReceive('sendReservationCancelled')->once()->with($lockedTrip, $passenger);

        self::assertTrue($this->service->cancelReservation($trip, 10, $passenger));
    }

    public function test_cancel_trip_sends_trip_cancellation_email_after_commit(): void
    {
        $driver = $this->makePerson(99, 'driver@example.test', 'Driver');
        $passenger = $this->makePerson(10, 'passenger@example.test', 'Passenger');
        $trip = $this->makeTrip(1, $driver, collect([$passenger]));

        $this->trips->shouldReceive('delete')->once()->with(1)->andReturnTrue();
        $this->tripEmails->shouldReceive('sendTripCancelledByDriver')->once()->with($trip);

        $this->service->cancelTrip($trip, $driver);

        self::assertTrue(true);
    }

    private function makePerson(int $id, string $email, string $firstName = 'User'): Person
    {
        $person = new Person;
        $person->id = $id;
        $person->first_name = $firstName;
        $person->last_name = 'Test';

        $user = new User;
        $user->email = $email;
        $user->role_id = 1;
        $user->is_active = true;

        $person->setRelation('user', $user);

        return $person;
    }

    private function makeTrip(int $id, Person $driver, Collection $passengers): Trip
    {
        $trip = new Trip;
        $trip->id = $id;
        $trip->person_id = $driver->id;
        $trip->available_seats = 3;
        $trip->departure_time = Carbon::parse('2026-02-20 10:00:00');
        $trip->arrival_time = Carbon::parse('2026-02-20 12:00:00');

        $this->attachTripRelations($trip, $driver, $passengers);

        return $trip;
    }

    private function attachTripRelations(Trip $trip, Person $driver, Collection $passengers): void
    {
        $departureCity = new City;
        $departureCity->postal_code = '75001';
        $departureCity->name = 'Paris';

        $arrivalCity = new City;
        $arrivalCity->postal_code = '69001';
        $arrivalCity->name = 'Lyon';

        $departureAddress = new Address;
        $departureAddress->street_number = '1';
        $departureAddress->street = 'Rue de Paris';
        $departureAddress->setRelation('city', $departureCity);

        $arrivalAddress = new Address;
        $arrivalAddress->street_number = '10';
        $arrivalAddress->street = 'Rue de Lyon';
        $arrivalAddress->setRelation('city', $arrivalCity);

        $trip->setRelation('driver', $driver);
        $trip->setRelation('departureAddress', $departureAddress);
        $trip->setRelation('arrivalAddress', $arrivalAddress);
        $trip->setRelation('passengers', $passengers);
    }
}

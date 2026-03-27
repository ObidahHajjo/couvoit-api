<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Resolvers\Interfaces\AddressResolverInterface;
use App\Services\Interfaces\OrsRoutingClientInterface;
use App\Services\Interfaces\TripEmailServiceInterface;
use App\Services\Interfaces\TripServiceInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class TripService
 *
 * Application service responsible for trip use-cases:
 * - Search trips
 * - Create/update/delete trips
 * - Reserve seats
 * - List passengers
 *
 * Business rules are enforced by policies in controllers,
 * but the service keeps defensive checks to avoid misuse.
 *
 * @author Application Service
 *
 * @description Manages trip operations including search, creation, updates, reservations, and cancellations.
 */
readonly class TripService implements TripServiceInterface
{
    /**
     * Create a new trip service instance.
     *
     * @param  TripRepositoryInterface  $trips  The trip repository
     * @param  PersonRepositoryInterface  $persons  The person repository
     * @param  AddressResolverInterface  $addressResolver  The address resolver
     * @param  AddressRepositoryInterface  $addresses  The address repository
     * @param  OrsRoutingClientInterface  $orsRoutingClient  The ORS routing client
     * @param  RepositoryCacheManager  $cache  The cache manager
     * @param  TripEmailServiceInterface  $tripEmails  The trip email service
     */
    public function __construct(
        private TripRepositoryInterface $trips,
        private PersonRepositoryInterface $persons,
        private AddressResolverInterface $addressResolver,
        private AddressRepositoryInterface $addresses,
        private OrsRoutingClientInterface $orsRoutingClient,
        private RepositoryCacheManager $cache,
        private TripEmailServiceInterface $tripEmails
    ) {}

    /**
     * Search for trips based on criteria.
     *
     * @param  string|null  $startingCity  The starting city
     * @param  string|null  $arrivalCity  The arrival city
     * @param  string|null  $tripDate  The trip date
     * @param  string|null  $tripTime  The minimum trip time on that date
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator<Trip> Paginated search results
     */
    public function searchTrips(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        ?string $tripTime,
        int $perPage = 15,
        ?int $excludePersonId = null
    ): LengthAwarePaginator {
        return $this->trips->search($startingCity, $arrivalCity, $tripDate, $tripTime, $perPage, $excludePersonId);
    }

    /**
     * Get passengers for a trip.
     *
     * @param  Trip  $trip  The trip
     * @return Collection<int, Person> Collection of passengers
     */
    public function getTripPassengers(Trip $trip): Collection
    {
        return $this->trips->passengers($trip);
    }

    /**
     * Create a new trip.
     *
     * @param  array  $payload  Trip data
     * @param  Person  $authPerson  The authenticated person
     * @return Trip The created trip
     *
     * @throws ValidationLogicException When validation fails
     * @throws ForbiddenException When user cannot create trip
     */
    public function createTrip(array $payload, Person $authPerson): Trip
    {
        $driverId = (int) ($payload['person_id'] ?? $authPerson->id);
        $user = $authPerson->user;
        $tripDateTime = Carbon::parse($payload['trip_datetime']);

        $availableSeats = (int) ($payload['available_seats'] ?? 0);
        $personCarAvailableSeats = $authPerson->car->seats;

        if ($availableSeats > $personCarAvailableSeats) {
            throw new ValidationLogicException('Available seats cannot be greater than the car\'s seats.');
        }

        if ($driverId !== $authPerson->id && ! $user->isAdmin()) {
            throw new ValidationLogicException('You cannot create a trip for another user.');
        }

        $driver = ($driverId === $authPerson->id)
            ? $authPerson
            : $this->getPersonById($driverId);

        if (is_null($driver->car_id)) {
            throw new ForbiddenException('Only drivers (persons with a car) can create trips.');
        }

        if ($tripDateTime->lessThanOrEqualTo(now())) {
            throw new ValidationLogicException(__('trip.date_time_in_past'));
        }
        return DB::transaction(function () use ($payload, $driver) {
            $departureAddressId = $this->addressResolver->resolveId($payload['starting_address']);
            $arrivalAddressId = $this->addressResolver->resolveId($payload['arrival_address']);

            $departure = $this->addresses->findOrFail($departureAddressId);
            $arrival = $this->addresses->findOrFail($arrivalAddressId);

            $depStr = sprintf(
                '%s %s, %s %s, France',
                $departure->street_number,
                $departure->street,
                $departure->city?->postal_code,
                $departure->city?->name
            );

            $arrStr = sprintf(
                '%s %s, %s %s, France',
                $arrival->street_number,
                $arrival->street,
                $arrival->city?->postal_code,
                $arrival->city?->name
            );

            $from = cache()->remember('geo:'.sha1($depStr), 86400, fn () => $this->orsRoutingClient->geocode($depStr));
            $to = cache()->remember('geo:'.sha1($arrStr), 86400, fn () => $this->orsRoutingClient->geocode($arrStr));

            $routeSummary = cache()->remember(
                'route:'.sha1(json_encode([$from, $to])),
                86400,
                fn () => $this->orsRoutingClient->routeSummary($from, $to)
            );

            $departureTime = Carbon::parse($payload['trip_datetime']);
            $durationSeconds = (int) $routeSummary['duration_seconds'];
            $arrivalTime = $departureTime->copy()->addSeconds($durationSeconds);

            $trip = $this->trips->create([
                'departure_time' => $payload['trip_datetime'],
                'distance_km' => $routeSummary['distance_km'],
                'available_seats' => $payload['available_seats'],
                'smoking_allowed' => (bool) ($payload['smoking_allowed'] ?? false),
                'departure_address_id' => $departureAddressId,
                'arrival_address_id' => $arrivalAddressId,
                'person_id' => $driver->id,
                'arrival_time' => $arrivalTime,
            ]);

            return $this->trips->findByIdOrFail((int) $trip->id);
        });
    }

    /**
     * Update a trip.
     *
     * @param  Trip  $trip  The trip to update
     * @param  array  $payload  Update data
     * @param  Person  $authPerson  The authenticated person
     * @return Trip The updated trip
     *
     * @throws ValidationLogicException When nothing to update
     */
    public function updateTrip(Trip $trip, array $payload, Person $authPerson): Trip
    {
        return DB::transaction(function () use ($trip, $payload) {
            $updates = [];

            if (array_key_exists('kms', $payload)) {
                $updates['distance_km'] = $payload['kms'];
            }
            if (array_key_exists('trip_datetime', $payload)) {
                $tripDateTime = Carbon::parse($payload['trip_datetime']);
                if ($tripDateTime->lessThanOrEqualTo(now())) {
                    throw new ValidationLogicException(__('trip.date_time_in_past'));
                }

                $updates['departure_time'] = $payload['trip_datetime'];
            }
            if (array_key_exists('available_seats', $payload)) {
                $updates['available_seats'] = $payload['available_seats'];
            }
            if (array_key_exists('smoking_allowed', $payload)) {
                $updates['smoking_allowed'] = (bool) $payload['smoking_allowed'];
            }

            if (! empty($payload['starting_address'] ?? null)) {
                $updates['departure_address_id'] = $this->addressResolver->resolveId($payload['starting_address']);
            }

            if (! empty($payload['arrival_address'] ?? null)) {
                $updates['arrival_address_id'] = $this->addressResolver->resolveId($payload['arrival_address']);
            }

            if (empty($updates)) {
                throw new ValidationLogicException('Nothing to update.');
            }

            $this->trips->update($trip->id, $updates);

            return $this->trips->findByIdOrFail($trip->id);
        });
    }

    /**
     * Cancel a trip.
     *
     * @param  Trip  $trip  The trip to cancel
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws ForbiddenException When trip has already started
     */
    public function cancelTrip(Trip $trip, Person $authPerson): void
    {
        $this->assertTripNotStarted($trip);

        $tripForEmail = $trip->loadMissing([
            'driver.user',
            'departureAddress.city',
            'arrivalAddress.city',
            'passengers.user',
        ]);

        DB::transaction(function () use ($trip, $tripForEmail) {
            $this->trips->delete($trip->id);

            DB::afterCommit(fn () => $this->sendEmailSafely(
                fn () => $this->tripEmails->sendTripCancelledByDriver($tripForEmail),
                'driver trip cancellation',
                ['trip_id' => $trip->id]
            ));
        });
    }

    /**
     * Permanently delete a trip.
     *
     * @param  Trip  $trip  The trip to delete
     * @param  Person  $authPerson  The authenticated person
     */
    public function deleteTripPermanently(Trip $trip, Person $authPerson): void
    {
        DB::transaction(function () use ($trip) {
            $this->trips->forceDelete($trip->id);
        });
    }

    /**
     * Reserve a seat on a trip.
     *
     * @param  Trip  $trip  The trip
     * @param  int  $personId  The person ID reserving
     * @param  Person  $authPerson  The authenticated person
     * @return bool True if reservation successful
     *
     * @throws ForbiddenException When user cannot reserve or trip started
     * @throws ValidationLogicException When driver tries to reserve own trip
     * @throws ConflictException When already reserved or no seats available
     */
    public function reserveSeat(Trip $trip, int $personId, Person $authPerson): bool
    {
        $user = $authPerson->user;
        if (! $user->isAdmin() && $personId !== $authPerson->id) {
            throw new ForbiddenException('You can only reserve for yourself.');
        }
        if ($trip->person_id === $personId) {
            throw new ValidationLogicException('Driver cannot reserve their own trip.');
        }

        $this->assertTripNotStarted($trip);

        return DB::transaction(function () use ($trip, $personId) {
            $lockedTrip = $this->trips->findByIdForUpdate($trip->id);

            $already = $lockedTrip->passengers()
                ->wherePivot('person_id', $personId)
                ->exists();

            if ($already) {
                throw new ConflictException('You already reserved this trip.');
            }

            $decremented = Trip::where('id', $lockedTrip->id)
                ->where('available_seats', '>', 0)
                ->update(['available_seats' => DB::raw('available_seats - 1')]);

            if ($decremented === 0) {
                throw new ConflictException('No available seats left.');
            }

            $lockedTrip->passengers()->attach($personId);

            $passenger = $this->getPersonById($personId)->loadMissing('user');
            $tripForEmail = $lockedTrip->loadMissing([
                'driver.user',
                'departureAddress.city',
                'arrivalAddress.city',
            ]);

            DB::afterCommit(function () use ($trip, $personId, $passenger, $tripForEmail) {
                $this->cache->invalidateReservationWrite(
                    $trip->id,
                    $personId,
                    $trip->person_id
                );

                $this->sendEmailSafely(
                    fn () => $this->tripEmails->sendReservationCreated($tripForEmail, $passenger),
                    'reservation confirmation',
                    ['trip_id' => $trip->id, 'person_id' => $personId]
                );
            });

            return true;
        });
    }

    /**
     * Cancel a reservation on a trip.
     *
     * @param  Trip  $trip  The trip
     * @param  int  $personId  The person ID canceling reservation
     * @param  Person  $authPerson  The authenticated person
     * @return bool True if cancellation successful
     *
     * @throws ForbiddenException When user cannot cancel or trip started
     * @throws NotFoundException When reservation not found
     */
    public function cancelReservation(Trip $trip, int $personId, Person $authPerson): bool
    {
        $user = $authPerson->user;
        if (! $user->isAdmin() && $personId !== $authPerson->id) {
            throw new ForbiddenException('You can only cancel for yourself.');
        }

        $this->assertTripNotStarted($trip);

        return DB::transaction(function () use ($trip, $personId) {
            $lockedTrip = $this->trips->findByIdForUpdate($trip->id);

            $passenger = $this->getPersonById($personId)->loadMissing('user');
            $tripForEmail = $lockedTrip->loadMissing([
                'driver.user',
                'departureAddress.city',
                'arrivalAddress.city',
            ]);

            $deleted = $lockedTrip->passengers()->detach($personId);
            if ($deleted === 0) {
                throw new NotFoundException('Reservation not found.');
            }

            Trip::where('id', $lockedTrip->id)
                ->update(['available_seats' => DB::raw('available_seats + 1')]);

            DB::afterCommit(function () use ($trip, $personId, $passenger, $tripForEmail) {
                $this->cache->invalidateReservationWrite(
                    $trip->id,
                    $personId,
                    $trip->person_id
                );

                $this->sendEmailSafely(
                    fn () => $this->tripEmails->sendReservationCancelled($tripForEmail, $passenger),
                    'reservation cancellation',
                    ['trip_id' => $trip->id, 'person_id' => $personId]
                );
            });

            return true;
        });
    }

    /**
     * Get a person by ID.
     *
     * @param  int  $personId  The person ID
     * @return Person The person
     */
    public function getPersonById(int $personId): Person
    {
        return $this->persons->findById($personId);
    }

    /**
     * Assert that a trip has not started.
     *
     * @param  Trip  $trip  The trip to check
     *
     * @throws ForbiddenException When trip has already started
     */
    private function assertTripNotStarted(Trip $trip): void
    {
        if ($trip->departure_time <= now()) {
            throw new ForbiddenException('Trip already started; action not allowed.');
        }
    }

    /**
     * Send a trip email callback and log failures.
     */
    private function sendEmailSafely(callable $callback, string $context, array $extra = []): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Failed to send trip email after '.$context.'.', array_merge($extra, [
                'exception' => $exception->getMessage(),
            ]));
        }
    }
}

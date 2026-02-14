<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Services\Interfaces\TripServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Resolvers\Interfaces\AddressResolverInterface;

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
 */
readonly class TripService implements TripServiceInterface
{
    public function __construct(
        private TripRepositoryInterface   $trips,
        private PersonRepositoryInterface $persons,
        private AddressResolverInterface  $addressResolver,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function searchTrips(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int     $perPage = 15
    ): LengthAwarePaginator
    {
        return $this->trips->search($startingCity, $arrivalCity, $tripDate, $perPage);
    }

    /**
     * @inheritDoc
     */
    public function getTripPassengers(Trip $trip): Collection
    {
        return $this->trips->passengers($trip);
    }

    /**
     * @inheritDoc
     */
    public function createTrip(array $payload, Person $authPerson): Trip
    {
        $driverId = (int)($payload['person_id'] ?? $authPerson->id);

        // Defensive check (policy should already handle)
        if ($driverId !== $authPerson->id && !$authPerson->isAdmin()) {
            throw new ForbiddenException('You cannot create a trip for another user.');
        }

        $driver = ($driverId === $authPerson->id)
            ? $authPerson
            : $this->getPersonById($driverId);

        if (is_null($driver->car_id)) {
            throw new ForbiddenException('Only drivers (persons with a car) can create trips.');
        }

        return DB::transaction(function () use ($payload, $driver, $authPerson) {
            $departureAddressId = $this->addressResolver->resolveId($payload['starting_address']);
            $arrivalAddressId = $this->addressResolver->resolveId($payload['arrival_address']);

            $trip = $this->trips->create([
                'departure_time' => $payload['trip_datetime'],
                'distance_km' => $payload['kms'],
                'available_seats' => $payload['available_seats'],
                'smoking_allowed' => (bool)($payload['smoking_allowed'] ?? false),
                'departure_address_id' => $departureAddressId,
                'arrival_address_id' => $arrivalAddressId,
                'person_id' => $driver->id,
            ]);

            // reload with eager-loaded relations from repository
            return $this->trips->findByIdOrFail((int)$trip->id);
        });
    }

    /**
     * @inheritDoc
     */
    public function updateTrip(Trip $trip, array $payload, Person $authPerson): Trip
    {
        return DB::transaction(function () use ($trip, $payload, $authPerson) {
            $updates = [];

            if (array_key_exists('kms', $payload)) {
                $updates['distance_km'] = $payload['kms'];
            }

            if (array_key_exists('trip_datetime', $payload)) {
                $updates['departure_time'] = $payload['trip_datetime'];
            }

            if (array_key_exists('available_seats', $payload)) {
                $updates['available_seats'] = $payload['available_seats'];
            }

            if (array_key_exists('smoking_allowed', $payload)) {
                $updates['smoking_allowed'] = (bool)$payload['smoking_allowed'];
            }

            if (!empty($payload['starting_address'] ?? null)) {
                $updates['departure_address_id'] = $this->addressResolver->resolveId($payload['starting_address']);
            }

            if (!empty($payload['arrival_address'] ?? null)) {
                $updates['arrival_address_id'] = $this->addressResolver->resolveId($payload['arrival_address']);
            }

            if (empty($updates)) throw new ValidationLogicException('Nothing to update.');

            $this->trips->update($trip->id, $updates);
            return $this->trips->findByIdOrFail($trip->id);
        });
    }

    /**
     * @inheritDoc
     */
    public function cancelTrip(Trip $trip, Person $authPerson): void
    {
        $this->assertTripNotStarted($trip);
        $this->trips->delete($trip->id); // soft delete (if Trip uses SoftDeletes)
    }


    /**
     * @inheritDoc
     */
    public function deleteTripPermanently(Trip $trip, Person $authPerson): void
    {
        $this->trips->forceDelete($trip->id);
    }

    /**
     * @inheritDoc
     */
    public function reserveSeat(Trip $trip, int $personId, Person $authPerson): bool
    {
        // Defensive check (policy should already handle)
        if (!$authPerson->isAdmin() && $personId !== $authPerson->id) throw new ForbiddenException('You can only reserve for yourself.');
        if ($trip->person_id === $personId) throw new ValidationLogicException('Driver cannot reserve their own trip.');
        $this->assertTripNotStarted($trip);

        return DB::transaction(function () use ($trip, $personId) {

            // Lock the trip row to prevent seat overbooking under concurrency
            $lockedTrip = $this->trips->findByIdForUpdate($trip->id);

            // Already reserved?
            $already = $lockedTrip->passengers()
                ->wherePivot('person_id', $personId)
                ->exists();

            if ($already) throw new ConflictException('You already reserved this trip.');


            // Current reservations count
            $reserved = $lockedTrip->passengers()->count();

            if ($reserved >= $lockedTrip->available_seats) throw new ConflictException('No available seats left.');

            // Attach to pivot table
            $lockedTrip->passengers()->attach($personId);

            return true;
        });
    }

    /**
     * @inheritDoc
     */
    public function cancelReservation(Trip $trip, int $personId, Person $authPerson): bool
    {
        if (!$authPerson->isAdmin() && $personId !== $authPerson->id) {
            throw new ForbiddenException('You can only cancel for yourself.');
        }

        $this->assertTripNotStarted($trip);

        $lockedTrip = $this->trips->findByIdForUpdate($trip->id);
        $deleted = $lockedTrip->passengers()->detach($personId);
        if ($deleted === 0) throw new NotFoundException('Reservation not found.');
        return true;

    }

    /**
     * @inheritDoc
     */
    public function getPersonById(int $personId): Person
    {
        return $this->persons->findById($personId);
    }

    private function assertTripNotStarted(Trip $trip): void
    {
        if ($trip->departure_time <= now()) throw new ForbiddenException('Trip already started; it cannot be canceled.');
    }
}

<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationLogicException;
use App\Models\Address;
use App\Models\City;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
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
        private TripRepositoryInterface $trips,
        private ReservationRepositoryInterface $reservations,
        private PersonRepositoryInterface $persons,
        private AddressResolverInterface $addressResolver,
    ) {}

    /**
     * @inheritDoc
     */
    public function searchTrips(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int $perPage = 15
    ): LengthAwarePaginator {
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
        if ($driverId !== $authPerson->id && ! $authPerson->isAdmin()) {
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
            $arrivalAddressId   = $this->addressResolver->resolveId($payload['arrival_address']);

            $trip = $this->trips->create([
                'departure_time'       => $payload['trip_datetime'],
                'distance_km'          => $payload['kms'],
                'available_seats'      => $payload['available_seats'],
                'smoking_allowed'      => (bool)($payload['smoking_allowed'] ?? false),
                'departure_address_id' => $departureAddressId,
                'arrival_address_id'   => $arrivalAddressId,
                'person_id'            => $driver->id,
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
                $updates['departure_address_id'] = $this->createAddressFromPayload(
                    $payload['starting_address']
                );
            }

            if (!empty($payload['arrival_address'] ?? null)) {
                $updates['arrival_address_id'] = $this->createAddressFromPayload(
                    $payload['arrival_address']
                );
            }

            if (empty($updates)) throw new ValidationLogicException('Nothing to update.');

            $ok = $this->trips->update($trip->id, $updates);
            if (!$ok) throw new NotFoundException('Trip not found.');

            return $this->trips->findByIdOrFail($trip->id);
        });
    }

    /**
     * @inheritDoc
     */
    public function deleteTrip(Trip $trip, Person $authPerson): void
    {
        DB::transaction(function () use ($trip) {
            $this->reservations->deleteByTrip($trip->id);

            $ok = $this->trips->delete($trip->id);
            if (!$ok) throw new NotFoundException('Trip not found.');
        });
    }

    /**
     * @inheritDoc
     */
    public function reserveSeat(Trip $trip, int $personId, Person $authPerson): bool
    {
        // Defensive check (policy should already handle)
        if (! $authPerson->isAdmin() && $personId !== $authPerson->id) {
            throw new ForbiddenException('You can only reserve for yourself.');
        }

        if ($trip->person_id === $personId) {
            throw new ValidationLogicException('Driver cannot reserve their own trip.');
        }

        return DB::transaction(function () use ($trip, $personId) {
            $count = $this->reservations->countByTrip($trip->id);

            if ($count >= $trip->available_seats) throw new ConflictException('No available seats left.');

            return $this->reservations->create($trip->id, $personId);
        });
    }

    /**
     * @inheritDoc
     */
    public function getPersonById(int $personId): Person
    {
        return $this->persons->findById($personId);
    }

    /**
     * Create City + Address from payload:
     * {
     *   street_number, street_name, postal_code, city_name
     * }
     *
     * @param array $addr
     * @return int Address id
     */
    private function createAddressFromPayload(array $addr): int
    {
        $cityName = trim((string)($addr['city_name'] ?? ''));
        $postal   = trim((string)($addr['postal_code'] ?? ''));

        $city = City::query()->firstOrCreate([
            'name' => mb_strtolower($cityName),
            'postal_code' => $postal,
        ]);

        $address = Address::query()->create([
            'street' => mb_strtolower(trim((string)($addr['street_name'] ?? ''))),
            'street_number' => trim((string)($addr['street_number'] ?? '')),
            'city_id' => (int)$city->id,
        ]);

        return (int)$address->id;
    }
}

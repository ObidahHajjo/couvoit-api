<?php

/**
 * @author    [Developer Name]
 *
 * @description Eloquent implementation of TripRepositoryInterface for managing Trip entities.
 */

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of TripRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - Trip by id:                 trips:{id} (tags: trips, trip:{id})
 * - Trip passengers:            trips:{id}:passengers (tags: trips, trip:{id}, reservations, persons)
 * - Search results (paginated): trips:search:* (tags: trips, trips:search)
 * - Lists by driver/passenger:  trips:driver:{personId}, trips:passenger:{personId}
 *
 * @implements TripRepositoryInterface
 */
readonly class TripEloquentRepository implements TripRepositoryInterface
{
    /**
     * Create a new trip repository instance.
     *
     * @param  RepositoryCacheManager  $cache  The cache manager for caching trip data.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Search for trips based on optional filters.
     *
     * @param  string|null  $startingCity  The departure city to filter by.
     * @param  string|null  $arrivalCity  The arrival city to filter by.
     * @param  string|null  $tripDate  The departure date to filter by.
     * @param  string|null  $tripTime  The minimum departure time on that date.
     * @param  int  $perPage  Number of results per page.
     * @return LengthAwarePaginator Paginated list of trips matching the criteria.
     */
    public function search(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        ?string $tripTime,
        int $perPage = 15,
        ?int $excludePersonId = null
    ): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);

        return $this->cache->rememberTripSearch(
            $startingCity,
            $arrivalCity,
            $tripDate,
            $tripTime,
            $perPage,
            $page,
            function () use ($startingCity, $arrivalCity, $tripDate, $tripTime, $perPage, $excludePersonId) {
                $query = Trip::query()
                    ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                    ->withCount(['passengers']);

                if ($excludePersonId) {
                    $query->where('person_id', '!=', $excludePersonId);
                }

                if ($startingCity) {
                    $query->whereHas('departureAddress.city', function ($qq) use ($startingCity) {
                        $qq->whereRaw('lower(name) = ?', [mb_strtolower($startingCity)]);
                    });
                }

                if ($arrivalCity) {
                    $query->whereHas('arrivalAddress.city', function ($qq) use ($arrivalCity) {
                        $qq->whereRaw('lower(name) = ?', [mb_strtolower($arrivalCity)]);
                    });
                }

                if ($tripDate && $tripTime) {
                    $query->where('departure_time', '>=', "$tripDate $tripTime")
                          ->where('departure_time', '<=', "$tripDate 23:59:59");
                } elseif ($tripDate) {
                    $query->whereDate('departure_time', '>=', $tripDate);
                } else {
                    $query->where('departure_time', '>', Carbon::now());
                }

                return $query
                    ->where('available_seats', '>', 0)
                    ->orderBy('departure_time')
                    ->paginate($perPage);
            },
            $excludePersonId
        );
    }

    /**
     * Find a trip by its ID.
     *
     * @param  int  $id  The ID of the trip to retrieve.
     * @return Trip|null The Trip entity if found, null otherwise.
     */
    public function findById(int $id): ?Trip
    {
        /** @var Trip|null $trip */
        $trip = $this->cache->rememberTripById($id, function () use ($id) {
            return Trip::query()
                ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                ->find($id);
        });

        return $trip;
    }

    /**
     * Find a trip by its ID or fail if not found.
     *
     * @param  int  $id  The ID of the trip to retrieve.
     * @return Trip The Trip entity.
     *
     * @throws ModelNotFoundException If trip is not found.
     */
    public function findByIdOrFail(int $id): Trip
    {
        /** @var Trip $trip */
        $trip = $this->cache->rememberTripById($id, function () use ($id) {
            return Trip::query()
                ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                ->findOrFail($id);
        });

        return $trip;
    }

    /**
     * Find a trip by its ID with a row lock for update.
     *
     * @param  int  $id  The ID of the trip to retrieve.
     * @return Trip The Trip entity with lock.
     *
     * @throws ModelNotFoundException If trip is not found.
     */
    public function findByIdForUpdate(int $id): Trip
    {
        return Trip::query()
            ->with(['departureAddress.city', 'arrivalAddress.city'])
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Create a new trip record.
     *
     * @param  array  $attributes  The data to create the trip with.
     * @return Trip The newly created Trip entity.
     */
    public function create(array $attributes): Trip
    {
        $trip = Trip::query()->create($attributes);

        $this->cache->invalidateTripWrite((int) $trip->id, (int) $trip->person_id);

        return $trip;
    }

    /**
     * Update an existing trip record.
     *
     * @param  int  $id  The ID of the trip to update.
     * @param  array  $attributes  The data to update the trip with.
     *
     * @throws ModelNotFoundException If trip is not found.
     */
    public function update(int $id, array $attributes): void
    {
        $trip = Trip::query()->findOrFail($id);
        $oldDriverId = (int) $trip->person_id;

        $trip->update($attributes);

        $this->cache->invalidateTripWrite(
            $id,
            (int) $trip->person_id,
            $oldDriverId
        );
    }

    /**
     * Soft delete a trip record.
     *
     * @param  int  $id  The ID of the trip to delete.
     * @return bool True if the operation was successful.
     *
     * @throws ModelNotFoundException If trip is not found.
     */
    public function delete(int $id): bool
    {
        $trip = Trip::query()->findOrFail($id);
        $driverId = (int) $trip->person_id;

        $ok = (bool) $trip->delete();

        $this->cache->invalidateTripWrite($id, $driverId);

        return $ok;
    }

    /**
     * Permanently delete a trip record.
     *
     * @param  int  $id  The ID of the trip to force delete.
     *
     * @throws ModelNotFoundException If trip is not found.
     */
    public function forceDelete(int $id): void
    {
        $trip = Trip::withTrashed()->findOrFail($id);
        $driverId = (int) $trip->person_id;

        $trip->forceDelete();

        $this->cache->invalidateTripWrite($id, $driverId);
    }

    /**
     * Get all passengers for a specific trip.
     *
     * @param  Trip  $trip  The trip to get passengers for.
     * @return Collection<int, Person> Collection of passengers.
     */
    public function passengers(Trip $trip): Collection
    {
        return $this->cache->rememberTripPassengers($trip->id, function () use ($trip) {
            return $trip->load('passengers.user.role', 'passengers.car')->passengers;
        });
    }

    /**
     * List all trips driven by a specific person.
     *
     * @param  int  $personId  The ID of the driver (person).
     * @return Collection<int, Trip> Collection of trips driven by the person.
     */
    public function listByDriver(int $personId): Collection
    {
        return $this->cache->rememberDriverTrips($personId, function () use ($personId) {
            return Trip::query()
                ->where('person_id', $personId)
                ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
                ->orderByDesc('departure_time')
                ->get();
        });
    }

    /**
     * List all trips taken by a specific passenger.
     *
     * @param  int  $personId  The ID of the passenger (person).
     * @return Collection<int, Trip> Collection of trips the person has participated in.
     */
    public function listByPassenger(int $personId): Collection
    {
        return $this->cache->rememberPassengerTrips($personId, function () use ($personId) {
            return Trip::query()
                ->whereHas('passengers', function ($query) use ($personId) {
                    $query->where('persons.id', $personId);
                })
                ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
                ->orderByDesc('departure_time')
                ->get();
        });
    }

    /**
     * Count total number of trips.
     *
     * @return int The total number of trips.
     */
    public function count(): int
    {
        return Trip::query()->count();
    }

    /**
     * Paginate all upcoming trips with relations for admin view.
     *
     * @param  int  $perPage  Number of results per page.
     * @return LengthAwarePaginator Paginated list of upcoming trips.
     */
    public function paginateForAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return Trip::query()
            ->with(['driver.car', 'departureAddress.city', 'arrivalAddress.city'])
            ->where('departure_time', '>', Carbon::now())
            ->paginate($perPage);
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
 */
readonly class TripEloquentRepository implements TripRepositoryInterface
{
    /**
     * Create a new trip repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /** @inheritDoc */
    public function search(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage = 15): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);

        return $this->cache->rememberTripSearch(
            $startingCity,
            $arrivalCity,
            $tripDate,
            $perPage,
            $page,
            function () use ($startingCity, $arrivalCity, $tripDate, $perPage) {
                $query = Trip::query()
                    ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                    ->withCount(['passengers']);

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

                if ($tripDate) {
                    $query->whereDate('departure_time', '=', $tripDate);
                }

                return $query
                    ->where('departure_time', '>', Carbon::now())
                    ->whereRaw('available_seats > (select count(*) from reservations where reservations.trip_id = trips.id)')
                    ->orderBy('departure_time')
                    ->paginate($perPage);
            }
        );
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
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

    /** @inheritDoc */
    public function findByIdForUpdate(int $id): Trip
    {
        return Trip::query()
            ->with(['departureAddress.city', 'arrivalAddress.city'])
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @inheritDoc */
    public function create(array $attributes): Trip
    {
        $trip = Trip::query()->create($attributes);

        $this->cache->invalidateTripWrite((int) $trip->id, (int) $trip->person_id);

        return $trip;
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $trip = Trip::query()->findOrFail($id);
        $driverId = (int) $trip->person_id;

        $ok = (bool) $trip->delete();

        $this->cache->invalidateTripWrite($id, $driverId);

        return $ok;
    }

    /** @inheritDoc */
    public function forceDelete(int $id): void
    {
        $trip = Trip::withTrashed()->findOrFail($id);
        $driverId = (int) $trip->person_id;

        $trip->forceDelete();

        $this->cache->invalidateTripWrite($id, $driverId);
    }

    /** @inheritDoc */
    public function passengers(Trip $trip): Collection
    {
        return $this->cache->rememberTripPassengers($trip->id, function () use ($trip) {
            return $trip->load('passengers.user.role', 'passengers.car')->passengers;
        });
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
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

    /** @inheritDoc */
    public function count(): int
    {
        return Trip::query()->count();
    }

    /** @inheritDoc */
    public function paginateForAdmin(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Trip::query()
            ->with(['driver.car', 'departureAddress.city', 'arrivalAddress.city'])
            ->where('departure_time', '>', Carbon::now())
            ->paginate($perPage);
    }
}

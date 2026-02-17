<?php

namespace App\Repositories\Eloquent;

use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TripEloquentRepository implements TripRepositoryInterface
{
    private const TTL_TRIP_SECONDS = 3600;
    private const TTL_SEARCH_SECONDS = 3600;
    private const TTL_PASSENGERS_SECONDS = 3600;
    private const TTL_LIST_SECONDS = 600;

    // ---------- Tags ----------
    private function tagTrips(): array
    {
        return ['trips'];
    }

    private function tagTrip(int $tripId): array
    {
        return ['trips', 'trip:' . $tripId];
    }

    private function tagSearch(): array
    {
        return ['trips', 'trips:search'];
    }

    private function tagPerson(int $personId): array
    {
        return ['trips', 'person:' . $personId];
    }

    private function tagPassengers(int $tripId): array
    {
        return ['trips', 'trip:' . $tripId, 'reservations', 'persons'];
    }

    // ---------- Keys ----------
    private function keyTrip(int $id): string
    {
        return 'trips:' . $id;
    }

    private function keyPassengers(int $id): string
    {
        return 'trips:' . $id . ':passengers';
    }

    private function keySearch(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage, int $page): string
    {
        return sprintf(
            'trips:search:%s:%s:%s:per:%d:page:%d',
            mb_strtolower($startingCity ?? 'any'),
            mb_strtolower($arrivalCity ?? 'any'),
            $tripDate ?? 'any',
            $perPage,
            $page
        );
    }

    private function keyDriverTrips(int $personId): string
    {
        return 'trips:driver:' . $personId;
    }

    private function keyPassengerTrips(int $personId): string
    {
        return 'trips:passenger:' . $personId;
    }

    /** @inheritDoc */
    public function search(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage = 15): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $key = $this->keySearch($startingCity, $arrivalCity, $tripDate, $perPage, $page);

        /** @var LengthAwarePaginator $p */
        $p = Cache::tags($this->tagSearch())
            ->remember($key, self::TTL_SEARCH_SECONDS, function () use ($startingCity, $arrivalCity, $tripDate, $perPage) {
                $q = Trip::query()->with(['driver', 'departureAddress.city', 'arrivalAddress.city']);

                if ($startingCity) {
                    $q->whereHas('departureAddress.city', fn ($qq) =>
                    $qq->whereRaw('lower(name) = ?', [mb_strtolower($startingCity)])
                    );
                }

                if ($arrivalCity) {
                    $q->whereHas('arrivalAddress.city', fn ($qq) =>
                    $qq->whereRaw('lower(name) = ?', [mb_strtolower($arrivalCity)])
                    );
                }

                if ($tripDate) {
                    $q->whereDate('departure_time', '=', $tripDate);
                }

                return $q->orderBy('departure_time')->paginate($perPage);
            });

        return $p;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Trip
    {
        /** @var Trip|null $trip */
        $trip = Cache::tags($this->tagTrip($id))
            ->remember($this->keyTrip($id), self::TTL_TRIP_SECONDS, function () use ($id) {
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
        $trip = Cache::tags($this->tagTrip($id))
            ->remember($this->keyTrip($id), self::TTL_TRIP_SECONDS, function () use ($id) {
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

        Cache::tags($this->tagTrip($trip->id))->flush();
        Cache::tags($this->tagPassengers($trip->id))->flush();
        Cache::tags($this->tagSearch())->flush();
        Cache::tags($this->tagPerson($trip->person_id))->flush();

        return $trip;
    }

    /** @inheritDoc */
    public function update(int $id, array $attributes): void
    {
        $trip = Trip::query()->findOrFail($id);
        $oldDriverId = $trip->person_id;

        $trip->update($attributes);

        Cache::tags($this->tagTrip($id))->flush();
        Cache::tags($this->tagPassengers($id))->flush();
        Cache::tags($this->tagSearch())->flush();

        Cache::tags($this->tagPerson($oldDriverId))->flush();
        Cache::tags($this->tagPerson($trip->person_id))->flush();
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $trip = Trip::query()->findOrFail($id);
        $driverId = $trip->person_id;

        $ok = (bool) $trip->delete();

        Cache::tags($this->tagTrip($id))->flush();
        Cache::tags($this->tagPassengers($id))->flush();
        Cache::tags($this->tagSearch())->flush();
        Cache::tags($this->tagPerson($driverId))->flush();

        return $ok;
    }

    /** @inheritDoc */
    public function forceDelete(int $id): void
    {
        $trip = Trip::withTrashed()->findOrFail($id);
        $driverId = $trip->person_id;

        $trip->forceDelete();

        Cache::tags($this->tagTrip($id))->flush();
        Cache::tags($this->tagPassengers($id))->flush();
        Cache::tags($this->tagSearch())->flush();
        Cache::tags($this->tagPerson($driverId))->flush();
    }

    /** @inheritDoc */
    public function passengers(Trip $trip): Collection
    {
        $id = $trip->id;

        /** @var Collection $passengers */
        $passengers = Cache::tags($this->tagPassengers($id))
            ->remember($this->keyPassengers($id), self::TTL_PASSENGERS_SECONDS, function () use ($trip) {
                return $trip->load('passengers.role', 'passengers.car')->passengers;
            });

        return $passengers;
    }

    /** @inheritDoc */
    public function listByDriver(int $personId): Collection
    {
        $key = $this->keyDriverTrips($personId);

        /** @var Collection $trips */
        $trips = Cache::tags(array_merge($this->tagPerson($personId), ['persons']))
            ->remember($key, self::TTL_LIST_SECONDS, function () use ($personId) {
                return Trip::query()
                    ->where('person_id', $personId)
                    ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
                    ->orderByDesc('departure_time')
                    ->get();
            });

        return $trips;
    }

    /** @inheritDoc */
    public function listByPassenger(int $personId): Collection
    {
        $key = $this->keyPassengerTrips($personId);

        /** @var Collection $trips */
        $trips = Cache::tags(array_merge($this->tagPerson($personId), ['reservations', 'persons']))
            ->remember($key, self::TTL_LIST_SECONDS, function () use ($personId) {
                return Trip::query()
                    ->whereHas('passengers', fn ($query) => $query->where('persons.id', $personId))
                    ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
                    ->orderByDesc('departure_time')
                    ->get();
            });

        return $trips;
    }
}

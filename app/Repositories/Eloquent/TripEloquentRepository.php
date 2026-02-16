<?php

namespace App\Repositories\Eloquent;

use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TripEloquentRepository implements TripRepositoryInterface
{
    private const TTL_TRIP_SECONDS = 60;
    private const TTL_SEARCH_SECONDS = 30;
    private const TTL_PASSENGERS_SECONDS = 15;

    private function keyTrip(int $id): string { return "trips:$id"; }
    private function keyPassengers(int $id): string { return "trips:$id:passengers"; }

    private function searchVersion(): int
    {
        return (int) Cache::get('trips:search:version', 1);
    }

    private function bumpSearchVersion(): void
    {
        Cache::add('trips:search:version', 1);
        Cache::increment('trips:search:version');
    }

    private function keySearch(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage, int $page): string
    {
        $v = $this->searchVersion();

        return sprintf(
            'trips:search:v%d:%s:%s:%s:per:%d:page:%d',
            $v,
            mb_strtolower($startingCity ?? 'any'),
            mb_strtolower($arrivalCity ?? 'any'),
            $tripDate ?? 'any',
            $perPage,
            $page
        );
    }

    /** @inheritDoc */
    public function search(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage = 15): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $key  = $this->keySearch($startingCity, $arrivalCity, $tripDate, $perPage, $page);

        /** @var LengthAwarePaginator $p */
        $p = Cache::remember($key, self::TTL_SEARCH_SECONDS, function () use ($startingCity, $arrivalCity, $tripDate, $perPage) {
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
        return Cache::remember($this->keyTrip($id), self::TTL_TRIP_SECONDS, function () use ($id) {
            return Trip::query()
                ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                ->find($id);
        });
    }

    /** @inheritDoc */
    public function findByIdOrFail(int $id): Trip
    {
        /** @var Trip $trip */
        $trip = Cache::remember($this->keyTrip($id), self::TTL_TRIP_SECONDS, function () use ($id) {
            return Trip::query()
                ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
                ->findOrFail($id);
        });

        return $trip;
    }

    /** @inheritDoc */
    public function findByIdForUpdate(int $id): Trip
    {
        // locking queries must hit DB (no cache)
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

        Cache::forget($this->keyTrip((int) $trip->id));
        Cache::forget($this->keyPassengers((int) $trip->id));
        $this->bumpSearchVersion();

        return $trip;
    }

    /** @inheritDoc */
    public function update(int $id, array $attributes): void
    {
        Trip::query()->findOrFail($id)->update($attributes);

        Cache::forget($this->keyTrip($id));
        Cache::forget($this->keyPassengers($id));
        $this->bumpSearchVersion();
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $ok = (bool) Trip::query()->findOrFail($id)->delete();

        Cache::forget($this->keyTrip($id));
        Cache::forget($this->keyPassengers($id));
        $this->bumpSearchVersion();

        return $ok;
    }

    /** @inheritDoc */
    public function forceDelete(int $id): void
    {
        // Requires SoftDeletes on Trip model
        Trip::withTrashed()->findOrFail($id)->forceDelete();

        Cache::forget($this->keyTrip($id));
        Cache::forget($this->keyPassengers($id));
        $this->bumpSearchVersion();
    }

    /** @inheritDoc */
    public function passengers(Trip $trip): Collection
    {
        $id = $trip->id;

        /** @var Collection $passengers */
        $passengers = Cache::remember($this->keyPassengers($id), self::TTL_PASSENGERS_SECONDS, function () use ($trip) {
            return $trip->load('passengers.role', 'passengers.car')->passengers;
        });
        return $passengers;
    }

    /** @inheritDoc */
    public function listByDriver(int $personId): Collection
    {
        $key = "trips:driver:{$personId}";

        return Cache::tags(['trips', "person:{$personId}"])
            ->remember($key, now()->addMinutes(2), function () use ($personId) {
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
        return Trip::query()
            ->whereHas('passengers', fn ($query) => $query->where('persons.id', $personId))
            ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
            ->orderByDesc('departure_time')
            ->get();
    }
}

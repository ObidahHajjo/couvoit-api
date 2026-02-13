<?php

namespace App\Repositories\Eloquent;

use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TripEloquentRepository implements TripRepositoryInterface
{
    public function search(?string $startingCity, ?string $arrivalCity, ?string $tripDate, int $perPage = 15): LengthAwarePaginator
    {
        $q = Trip::query()
            ->with(['driver', 'departureAddress.city', 'arrivalAddress.city']);

         $q->whereNull('deleted_at');

        if ($startingCity) {
            $q->whereHas('departureAddress.city', function ($qq) use ($startingCity) {
                $qq->whereRaw('lower(name) = ?', [mb_strtolower($startingCity)]);
            });
        }

        if ($arrivalCity) {
            $q->whereHas('arrivalAddress.city', function ($qq) use ($arrivalCity) {
                $qq->whereRaw('lower(name) = ?', [mb_strtolower($arrivalCity)]);
            });
        }

        if ($tripDate) {
            $q->whereDate('departure_time', '=', $tripDate);
        }

        return $q->orderBy('departure_time')->paginate($perPage);
    }

    public function findById(int $id): ?Trip
    {
        return Trip::query()
            ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
            ->find($id);
    }

    public function findByIdOrFail(int $id): Trip
    {
        return Trip::query()
            ->with(['driver', 'departureAddress.city', 'arrivalAddress.city'])
            ->findOrFail($id);
    }

    public function create(array $attributes): Trip
    {
        return Trip::query()->create($attributes);
    }

    public function update(int $id, array $attributes): bool
    {
        return (bool) Trip::query()->whereKey($id)->update($attributes);
    }

    public function delete(int $id): bool
    {
        return (bool) Trip::query()->whereKey($id)->delete();
    }

    public function passengers(Trip $trip): Collection
    {
        return $trip->load('passengers.role', 'passengers.car')->passengers;
    }

    public function listByDriver(int $personId): Collection
    {
        return Trip::query()
            ->where('person_id', $personId)
            ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
            ->orderByDesc('departure_time')
            ->get();
    }

    public function listByPassenger(int $personId): Collection
    {
        return Trip::query()
            ->whereHas('passengers', function ($query) use ($personId) {
                $query->where('persons.id', $personId);
            })
            ->with(['departureAddress.city', 'arrivalAddress.city', 'driver'])
            ->orderByDesc('departure_time')
            ->get();
    }
}

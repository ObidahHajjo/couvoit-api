<?php

namespace App\Repositories\Interfaces;

use App\Models\Person;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TripRepositoryInterface
{
    /**
     * Search trips with optional filters:
     * - startingcity (string)
     * - arrivalcity (string)
     * - tripdate (Y-m-d)
     */
    public function search(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int     $perPage = 15
    ): LengthAwarePaginator;

    public function findById(int $id): ?Trip;

    /**
     * Useful when service wants a fresh copy with relations loaded
     * and prefers exception on missing.
     */
    public function findByIdOrFail(int $id): Trip;

    public function findByIdForUpdate(int $id): Trip;

    public function create(array $attributes): Trip;

    public function update(int $id, array $attributes): void;

    public function delete(int $id): bool;

    public function forceDelete(int $id): void;

    /**
     * Passengers list for a given trip.
     */
    public function passengers(Trip $trip): Collection;

    public function listByDriver(int $personId): Collection;
    public function listByPassenger(int $personId): Collection;
}

<?php

namespace App\Repositories\Interfaces;

use App\Models\Person;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TripRepositoryInterface
{
    /**
     * Search trips by optional cities and date, returning a paginator.
     *
     * Cache key includes query params and page number.
     *
     * @param string|null $startingCity
     * @param string|null $arrivalCity
     * @param string|null $tripDate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int     $perPage = 15
    ): LengthAwarePaginator;

    /**
     * Find a trip by id.
     *
     * @param int $id
     * @return Trip|null
     */
    public function findById(int $id): ?Trip;

    /**
     * Find a trip by id or fail.
     *
     * @param int $id
     * @return Trip
     */
    public function findByIdOrFail(int $id): Trip;

    /**
     * Find a trip row for update (pessimistic lock).
     *
     * No caching is used because the row is locked for write.
     *
     * @param int $id
     * @return Trip
     */
    public function findByIdForUpdate(int $id): Trip;

    /**
     * Create a trip and invalidate relevant cache scopes.
     *
     * @param array<string,mixed> $attributes
     * @return Trip
     */
    public function create(array $attributes): Trip;

    /**
     * Update a trip and invalidate relevant cache scopes.
     *
     * @param int $id
     * @param array<string,mixed> $attributes
     * @return void
     */
    public function update(int $id, array $attributes): void;

    /**
     * Soft-delete a trip and invalidate relevant cache scopes.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Permanently delete a soft-deleted trip and invalidate relevant cache scopes.
     *
     * @param int $id
     * @return void
     */
    public function forceDelete(int $id): void;

    /**
     * Retrieve passengers for a trip (cached).
     *
     * @param Trip $trip
     * @return Collection<int,mixed>
     */
    public function passengers(Trip $trip): Collection;

    /**
     * List trips where the given person is the driver (cached).
     *
     * @param int $personId
     * @return Collection<int,mixed>
     */
    public function listByDriver(int $personId): Collection;

    /**
     * List trips where the given person is a passenger (cached).
     *
     * @param int $personId
     * @return Collection<int,mixed>
     */
    public function listByPassenger(int $personId): Collection;
}

<?php

namespace App\Repositories\Interfaces;

use App\Models\Car;
use Illuminate\Support\Collection;

/**
 * Contract for car persistence operations.
 */
interface CarRepositoryInterface
{
    /**
     * Retrieve all cars.
     *
     * @return Collection<int,Car>
     */
    public function all(): Collection;

    /**
     * Find a car by its identifier.
     *
     * @param int $id
     * @return Car|null
     */
    public function find(int $id): ?Car;

    /**
     * Find a car by its identifier or fail.
     *
     * @param int $id
     * @return Car
     */
    public function findOrFail(int $id): Car;

    /**
     * Persist a new car and update caches.
     *
     * @param array<string,mixed> $data
     * @return Car
     */
    public function create(array $data): Car;

    /**
     * Update the given car and refresh caches.
     *
     * @param Car                $car
     * @param array<string,mixed> $data
     * @return bool
     */
    public function update(Car $car, array $data): bool;

    /**
     * Delete the given car and invalidate caches.
     *
     * @param Car $car
     * @return void
     */
    public function delete(Car $car): void;

    /**
     * Get paginated cars for admin.
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateForAdmin(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
}

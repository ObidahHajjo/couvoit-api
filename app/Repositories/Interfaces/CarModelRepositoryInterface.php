<?php

namespace App\Repositories\Interfaces;

use App\Models\CarModel;
use Illuminate\Support\Collection;

/**
 * Contract for car model persistence operations.
 */
interface CarModelRepositoryInterface
{
    /**
     * Retrieve all car models.
     *
     * @return Collection<int,CarModel>
     */
    public function all(): Collection;

    /**
     * Find a car model by its identifier.
     *
     * @param int $id
     * @return CarModel|null
     */
    public function findById(int $id): ?CarModel;

    /**
     * Create a model if it does not exist, or return the existing one.
     *
     * @param array<string,mixed> $data
     * @return CarModel
     */
    public function createOrFirst(array $data): CarModel;

    /**
     * Update the given model and refresh caches.
     *
     * @param CarModel            $model
     * @param array<string,mixed> $data
     * @return void
     */
    public function update(CarModel $model, array $data): void;

    /**
     * Delete the given model and invalidate caches.
     *
     * @param CarModel $model
     * @return bool
     */
    public function delete(CarModel $model): bool;

    /**
     * Find a model by its (case-insensitive) name.
     *
     * @param string $name
     * @return CarModel|null
     */
    public function findByName(string $name): ?CarModel;

    /**
     * Find all models belonging to a given brand.
     *
     * @param int $brandId
     * @return Collection<int,CarModel>
     */
    public function findByBrand(int $brandId): Collection;

    /**
     * Find a model by its search_key and brand name
     *
     * @param string $brandSearchKey
     * @param string $modelSearchKey
     *
     * @return Collection<int, CarModel>
     */
    public function findBySearchKey(string $brandSearchKey, string $modelSearchKey): Collection;

    /**
     * Create a new car model.
     *
     * @param array<string, mixed> $attributes
     * @return CarModel
     */
    public function create(array $attributes): CarModel;

    /**
     * Get paginated car models for admin.
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateForAdmin(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Check if model has cars.
     *
     * @param CarModel $model
     * @return bool
     */
    public function hasCars(CarModel $model): bool;
}

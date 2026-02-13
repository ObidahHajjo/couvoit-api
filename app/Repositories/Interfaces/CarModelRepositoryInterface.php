<?php

namespace App\Repositories\Interfaces;

use App\Models\CarModel;
use Illuminate\Support\Collection;

interface CarModelRepositoryInterface
{
    /**
     * Retrieve all car models with their brand.
     *
     * @return Collection<int,CarModel>
     */
    public function all(): Collection;

    /**
     * Find a car model by its id.
     *
     * @param int $id
     * @return CarModel|null
     */
    public function findById(int $id): ?CarModel;

    /**
     * Create a model or return the first matching one.
     *
     * Unique key assumed:
     * - name
     * - brand_id
     *
     * @param array{name:string,brand_id:int,seats:int,type_id:int} $data
     * @return CarModel
     */
    public function createOrFirst(array $data): CarModel;

    /**
     * Update a given CarModel.
     *
     * @param CarModel $model
     * @param array $data
     *
     * @return bool
     */
    public function update(CarModel $model, array $data): bool;

    /**
     * Delete a given CarModel.
     *
     * @param CarModel $model
     * @return bool
     */
    public function delete(CarModel $model): bool;

    /**
     * Find a model by name.
     *
     * @param string $name
     * @return CarModel|null
     */
    public function findByName(string $name): ?CarModel;

    /**
     * Find all models for a given brand.
     *
     * @param int $brandId
     * @return Collection<int,CarModel>
     */
    public function findByBrand(int $brandId): Collection;
}

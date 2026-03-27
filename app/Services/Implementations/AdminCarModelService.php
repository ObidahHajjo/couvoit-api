<?php

/**
 * @author Admin
 *
 * @description Service implementation for managing car model operations in the admin panel.
 */

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Services\Interfaces\AdminCarModelServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service implementation for admin car model management operations.
 */
readonly class AdminCarModelService implements AdminCarModelServiceInterface
{
    public function __construct(
        private CarModelRepositoryInterface $models,
    ) {}

    /**
     * List all car models with pagination.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of car models
     */
    public function listModels(int $perPage = 15): LengthAwarePaginator
    {
        return $this->models->paginateForAdmin($perPage);
    }

    /**
     * Create a new car model.
     *
     * @param  array  $data  Car model data including name, brand_id, type_id and other attributes
     * @return CarModel The created car model instance
     */
    public function createModel(array $data): CarModel
    {
        return $this->models->create($data);
    }

    /**
     * Update an existing car model.
     *
     * @param  CarModel  $model  The car model to update
     * @param  array  $data  Updated car model data
     * @return CarModel The updated car model instance
     */
    public function updateModel(CarModel $model, array $data): CarModel
    {
        $this->models->update($model, $data);

        return $this->models->findById($model->id) ?? $model;
    }

    /**
     * Delete a car model.
     *
     * @param  CarModel  $model  The car model to delete
     *
     * @throws ConflictException When the model has associated cars
     */
    public function deleteModel(CarModel $model): void
    {
        if ($this->models->hasCars($model)) {
            throw new ConflictException('Cannot delete model assigned to existing cars.');
        }

        $this->models->delete($model);
    }
}

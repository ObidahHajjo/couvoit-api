<?php

namespace App\Services\Interfaces;

use App\Models\CarModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for admin car model management services.
 */
interface AdminCarModelServiceInterface
{
    /**
     * List all car models with pagination.
     *
     * @param  int  $perPage  Number of items per page.
     * @return LengthAwarePaginator<int, CarModel>
     */
    public function listModels(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new car model.
     *
     * @param  array<string, mixed>  $data  Car model creation data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function createModel(array $data): CarModel;

    /**
     * Update an existing car model.
     *
     * @param  CarModel  $model  Car model to update.
     * @param  array<string, mixed>  $data  Car model update data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function updateModel(CarModel $model, array $data): CarModel;

    /**
     * Delete a car model.
     *
     * @param  CarModel  $model  Car model to delete.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteModel(CarModel $model): void;
}

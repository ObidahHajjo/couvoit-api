<?php

namespace App\Services\Interfaces;

use App\Models\Car;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for admin car management services.
 */
interface AdminCarServiceInterface
{
    /**
     * List all cars with pagination.
     *
     * @param  int  $perPage  Number of items per page.
     * @return LengthAwarePaginator<int, Car>
     */
    public function listCars(int $perPage = 15): LengthAwarePaginator;

    /**
     * Delete a car.
     *
     * @param  Car  $car  Car to delete.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteCar(Car $car): void;
}

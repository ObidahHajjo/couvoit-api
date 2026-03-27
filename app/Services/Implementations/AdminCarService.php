<?php

/**
 * @author Admin
 *
 * @description Service implementation for managing car operations in the admin panel.
 */

namespace App\Services\Implementations;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Services\Interfaces\AdminCarServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service implementation for admin car management operations.
 */
readonly class AdminCarService implements AdminCarServiceInterface
{
    public function __construct(
        private CarRepositoryInterface $cars,
    ) {}

    /**
     * List all cars with pagination.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of cars
     */
    public function listCars(int $perPage = 15): LengthAwarePaginator
    {
        return $this->cars->paginateForAdmin($perPage);
    }

    /**
     * Delete a car.
     *
     * @param  Car  $car  The car to delete
     */
    public function deleteCar(Car $car): void
    {
        $this->cars->delete($car);
    }
}

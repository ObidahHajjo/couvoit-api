<?php

namespace App\Services\Implementations;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Services\Interfaces\AdminCarServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class AdminCarService implements AdminCarServiceInterface
{
    public function __construct(
        private CarRepositoryInterface $cars,
    ) {}

    public function listCars(int $perPage = 15): LengthAwarePaginator
    {
        return $this->cars->paginateForAdmin($perPage);
    }

    public function deleteCar(Car $car): void
    {
        $this->cars->delete($car);
    }
}

<?php

namespace App\Services\Interfaces;

use App\Models\Car;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdminCarServiceInterface
{
    public function listCars(int $perPage = 15): LengthAwarePaginator;

    public function deleteCar(Car $car): void;
}

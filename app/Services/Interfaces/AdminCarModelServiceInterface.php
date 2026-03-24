<?php

namespace App\Services\Interfaces;

use App\Models\CarModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdminCarModelServiceInterface
{
    public function listModels(int $perPage = 15): LengthAwarePaginator;

    public function createModel(array $data): CarModel;

    public function updateModel(CarModel $model, array $data): CarModel;

    public function deleteModel(CarModel $model): void;
}

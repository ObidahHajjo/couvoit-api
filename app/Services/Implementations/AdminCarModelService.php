<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Services\Interfaces\AdminCarModelServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class AdminCarModelService implements AdminCarModelServiceInterface
{
    public function __construct(
        private CarModelRepositoryInterface $models,
    ) {}

    public function listModels(int $perPage = 15): LengthAwarePaginator
    {
        return $this->models->paginateForAdmin($perPage);
    }

    public function createModel(array $data): CarModel
    {
        return $this->models->create($data);
    }

    public function updateModel(CarModel $model, array $data): CarModel
    {
        $this->models->update($model, $data);

        return $this->models->findById($model->id) ?? $model;
    }

    public function deleteModel(CarModel $model): void
    {
        if ($this->models->hasCars($model)) {
            throw new ConflictException('Cannot delete model assigned to existing cars.');
        }

        $this->models->delete($model);
    }
}

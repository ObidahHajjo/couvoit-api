<?php

namespace App\Repositories\Eloquent;

use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Class CarModelEloquentRepository
 *
 * Eloquent implementation of CarModelRepositoryInterface.
 * Responsible only for persistence logic related to CarModel.
 */
class CarModelEloquentRepository implements CarModelRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        return CarModel::query()
            ->with('brand')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?CarModel
    {
        return CarModel::query()
            ->with('brand')
            ->find($id);
    }

    /**
     * @inheritDoc
     */
    public function createOrFirst(array $data): CarModel
    {
        return CarModel::query()->createOrFirst(
            [
                'name' => $data['name'],
                'brand_id' => $data['brand_id'],
            ],
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function update(CarModel $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * @inheritDoc
     */
    public function delete(CarModel $model): bool
    {
        return $model->delete();
    }

    /**
     * @inheritDoc
     */
    public function findByName(string $name): ?CarModel
    {
        return CarModel::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function findByBrand(int $brandId): Collection
    {
        return CarModel::query()
            ->where('brand_id', $brandId)
            ->get();
    }
}

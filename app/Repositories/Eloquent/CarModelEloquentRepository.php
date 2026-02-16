<?php

namespace App\Repositories\Eloquent;

use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CarModelEloquentRepository implements CarModelRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function keyAll(): string
    {
        return 'models:all';
    }

    private function keyById(int $id): string
    {
        return "models:$id";
    }

    private function keyByBrand(int $brandId): string
    {
        return "models:brand:$brandId";
    }

    private function keyByName(string $name): string
    {
        return 'models:name:' . mb_strtolower(trim($name));
    }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return CarModel::query()->with('brand')->get();
        });

        return $cached;
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?CarModel
    {
        return Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return CarModel::query()->with('brand')->find($id);
        });
    }

    /**
     * @inheritDoc
     */
    public function createOrFirst(array $data): CarModel
    {
        $model = CarModel::query()->createOrFirst(
            [
                'name' => $data['name'],
                'brand_id' => $data['brand_id'],
            ],
            $data
        );

        // write-through updates
        Cache::put($this->keyById((int)$model->id), $model->loadMissing('brand'), self::TTL_SECONDS);
        Cache::forget($this->keyAll());
        Cache::forget($this->keyByBrand((int)$model->brand_id));
        Cache::forget($this->keyByName((string)$model->name));

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function update(CarModel $model, array $data): void
    {
        $model->update($data);
        $model->refresh()->loadMissing('brand');

        Cache::put($this->keyById($model->id), $model, self::TTL_SECONDS);
        Cache::forget($this->keyAll());
        Cache::forget($this->keyByBrand($model->brand_id));
        Cache::forget($this->keyByName($model->name));
    }

    /**
     * @inheritDoc
     */
    public function delete(CarModel $model): bool
    {
        $id = $model->id;
        $brandId = $model->brand_id;
        $name = $model->name;

        $ok = (bool)$model->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyAll());
        Cache::forget($this->keyByBrand($brandId));
        Cache::forget($this->keyByName($name));

        return $ok;
    }

    /**
     * @inheritDoc
     */
    public function findByName(string $name): ?CarModel
    {
        $key = $this->keyByName($name);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($name) {
            return CarModel::query()->where('name', $name)->first();
        });
    }

    /**
     * @inheritDoc
     */
    public function findByBrand(int $brandId): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyByBrand($brandId), self::TTL_SECONDS, function () use ($brandId) {
            return CarModel::query()->where('brand_id', $brandId)->get();
        });

        return $cached;
    }
}

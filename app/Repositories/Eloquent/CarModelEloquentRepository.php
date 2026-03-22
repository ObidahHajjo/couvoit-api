<?php

namespace App\Repositories\Eloquent;

use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of CarModelRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - Global list: models:all (tag: models)
 * - By id:       models:{id} (tags: models, model:{id})
 * - By brand:    models:brand:{brandId} (tags: models, brand:{brandId})
 * - By name:     models:name:{normalizedName} (tags: models, name:{normalizedName})
 */
readonly class CarModelEloquentRepository implements CarModelRepositoryInterface
{
    /**
     * Create a new car model repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /**
     * Normalize model name for consistent caching and lookup.
     *
     * @param string $name
     * @return string
     */
    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,CarModel> $models */
        $models = $this->cache->rememberModelsAll(function () {
            return CarModel::query()
                ->with(['brand', 'type'])
                ->get();
        });

        foreach ($models as $model) {
            $this->cache->putModel($model);
        }

        return $models;
    }

    /** @inheritDoc */
    public function findById(int $id): ?CarModel
    {
        /** @var CarModel|null $model */
        $model = $this->cache->rememberModelById($id, function () use ($id) {
            return CarModel::query()
                ->with(['brand', 'type'])
                ->find($id);
        });

        return $model;
    }

    /** @inheritDoc */
    public function createOrFirst(array $data): CarModel
    {
        $data['name'] = $this->normalizeName((string) ($data['name'] ?? ''));

        $model = CarModel::query()->updateOrCreate(
            [
                'name' => $data['name'],
                'brand_id' => $data['brand_id'],
                'search_key' => $data['search_key'],
            ],
            $data
        )->load(['brand', 'type']);

        $this->cache->putModel($model);
        $this->cache->forgetModelsAll();
        $this->cache->forgetModelsByBrand((int) $model->brand_id);
        $this->cache->invalidateCarsAndPersonsByModelId((int) $model->id);

        return $model;
    }

    /** @inheritDoc */
    public function update(CarModel $model, array $data): void
    {
        $oldBrandId = $model->brand_id;
        $oldName = $model->name;

        if (array_key_exists('name', $data)) {
            $data['name'] = $this->normalizeName((string) $data['name']);
        }

        $model->update($data);
        $model->refresh()->load(['brand', 'type']);

        $this->cache->putModel($model);
        $this->cache->forgetModelsAll();
        $this->cache->forgetModelsByBrand($oldBrandId);
        $this->cache->forgetModelsByBrand($model->brand_id);

        if ($oldName !== $model->name) {
            $this->cache->forgetModelByName($oldName);
        }

        $this->cache->invalidateCarsAndPersonsByModelId($model->id);
    }

    /** @inheritDoc */
    public function delete(CarModel $model): bool
    {
        $id = $model->id;
        $brandId = $model->brand_id;
        $name = $model->name;

        $ok = (bool) $model->delete();

        $this->cache->forgetModel($id);
        $this->cache->forgetModelsByBrand($brandId);
        $this->cache->forgetModelByName($name);
        $this->cache->forgetModelsAll();
        $this->cache->invalidateCarsAndPersonsByModelId($id);

        return $ok;
    }

    /** @inheritDoc */
    public function findByName(string $name): ?CarModel
    {
        /** @var CarModel|null $model */
        $model = $this->cache->rememberModelByName($name, function () use ($name) {
            $normalized = $this->normalizeName($name);

            return CarModel::query()
                ->with(['brand', 'type'])
                ->whereRaw('lower(name) = ?', [$normalized])
                ->first();
        });

        if ($model) {
            $this->cache->putModel($model);
        }

        return $model;
    }

    /** @inheritDoc */
    public function findByBrand(int $brandId): Collection
    {
        /** @var Collection<int,CarModel> $models */
        $models = $this->cache->rememberModelsByBrand($brandId, function () use ($brandId) {
            return CarModel::query()
                ->with(['brand', 'type'])
                ->where('brand_id', $brandId)
                ->orderBy('name')
                ->get();
        });

        foreach ($models as $model) {
            $this->cache->putModel($model);
        }

        return $models;
    }

    /** @inheritDoc */
    public function findBySearchKey(string $brandSearchKey, string $modelSearchKey): Collection{
        return CarModel::query()
            ->with('brand')
            ->whereHas('brand', function ($query) use ($brandSearchKey) {
                $query->where('name', $brandSearchKey);
            })
            ->where('search_key', 'like', '%' . $modelSearchKey . '%')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }
}

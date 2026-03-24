<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
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
 *
 * @author Covoiturage API
 *
 * @description Repository for managing CarModel entities with caching support.
 */
readonly class CarModelEloquentRepository implements CarModelRepositoryInterface
{
    /**
     * Create a new car model repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Normalize model name for consistent caching and lookup.
     */
    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Get all car models with brand and type relations.
     *
     * @return Collection<int, CarModel> Collection of all CarModel instances
     */
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

    /**
     * Find a car model by its ID.
     *
     * @param  int  $id  The car model ID to find
     * @return CarModel|null The CarModel instance with brand and type relations if found
     */
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

    /**
     * Create a new car model or update existing one.
     *
     * @param  array<string, mixed>  $data  CarModel data including name, brand_id, search_key
     * @return CarModel The created or updated CarModel instance with relations
     */
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

    /**
     * Update a car model with new data.
     *
     * @param  CarModel  $model  The CarModel instance to update
     * @param  array<string, mixed>  $data  New data to apply
     */
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

    /**
     * Delete a car model.
     *
     * @param  CarModel  $model  The CarModel instance to delete
     * @return bool True if deletion was successful
     *
     * @throws \Exception When database deletion fails
     */
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

    /**
     * Find a car model by its name.
     *
     * @param  string  $name  The car model name to search for (case-insensitive)
     * @return CarModel|null The CarModel instance with brand and type if found
     */
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

    /**
     * Find all car models for a specific brand.
     *
     * @param  int  $brandId  The brand ID to filter by
     * @return Collection<int, CarModel> Collection of CarModel instances ordered by name
     */
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

    /**
     * Search car models by brand name and model search key.
     *
     * @param  string  $brandSearchKey  Brand name to search for
     * @param  string  $modelSearchKey  Model search key pattern (supports wildcards)
     * @return Collection<int, CarModel> Collection of matching CarModels (max 20)
     */
    public function findBySearchKey(string $brandSearchKey, string $modelSearchKey): Collection
    {
        return CarModel::query()
            ->with('brand')
            ->whereHas('brand', function ($query) use ($brandSearchKey) {
                $query->where('name', $brandSearchKey);
            })
            ->where('search_key', 'like', '%'.$modelSearchKey.'%')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * Create a new car model.
     *
     * @param  array<string, mixed>  $attributes  CarModel attributes to create
     * @return CarModel The newly created CarModel instance with relations
     *
     * @throws QueryException When creation fails
     */
    public function create(array $attributes): CarModel
    {
        if (isset($attributes['name'])) {
            $attributes['name'] = $this->normalizeName($attributes['name']);
        }

        $model = CarModel::query()->create($attributes);
        $model->load(['brand', 'type']);

        $this->cache->putModel($model);
        $this->cache->forgetModelsAll();
        $this->cache->forgetModelsByBrand((int) $model->brand_id);

        return $model;
    }

    /**
     * Paginate all car models for admin panel.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of CarModel instances with relations
     */
    public function paginateForAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return CarModel::query()
            ->with(['brand', 'type'])
            ->paginate($perPage);
    }

    /**
     * Check if a car model has associated cars.
     *
     * @param  CarModel  $model  The CarModel instance to check
     * @return bool True if model has cars, false otherwise
     */
    public function hasCars(CarModel $model): bool
    {
        return Car::query()->where('model_id', $model->id)->exists();
    }
}

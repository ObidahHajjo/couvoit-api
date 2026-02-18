<?php

namespace App\Repositories\Eloquent;

use App\Models\CarModel;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
class CarModelEloquentRepository implements CarModelRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    // ---------- Tags ----------

    /**
     * @return array<int,string>
     */
    private function tagModels(): array
    {
        return ['models'];
    }

    /**
     * @param int $id
     * @return array<int,string>
     */
    private function tagModel(int $id): array
    {
        return ['models', 'model:' . $id];
    }

    /**
     * @param int $brandId
     * @return array<int,string>
     */
    private function tagBrand(int $brandId): array
    {
        return ['models', 'brand:' . $brandId];
    }

    /**
     * @param string $name
     * @return array<int,string>
     */
    private function tagName(string $name): array
    {
        return ['models', 'name:' . $this->normalizeName($name)];
    }

    // ---------- Keys ----------

    private function keyAll(): string
    {
        return 'models:all';
    }

    private function keyById(int $id): string
    {
        return 'models:' . $id;
    }

    private function keyByBrand(int $brandId): string
    {
        return 'models:brand:' . $brandId;
    }

    private function keyByName(string $name): string
    {
        return 'models:name:' . $this->normalizeName($name);
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
        $models = Cache::tags($this->tagModels())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return CarModel::query()
                    ->with('brand')
                    ->get();
            });

        // Optional: warm per-model + per-name caches (avoid re-caching by-brand for each model)
        foreach ($models as $m) {
            Cache::tags($this->tagModel($m->id))
                ->put($this->keyById($m->id), $m, self::TTL_SECONDS);

            Cache::tags($this->tagName($m->name))
                ->put($this->keyByName($m->name), $m, self::TTL_SECONDS);
        }

        return $models;
    }

    /** @inheritDoc */
    public function findById(int $id): ?CarModel
    {
        /** @var CarModel|null $model */
        $model = Cache::tags($this->tagModel($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return CarModel::query()
                    ->with('brand')
                    ->find($id);
            });

        return $model;
    }

    /** @inheritDoc */
    public function createOrFirst(array $data): CarModel
    {
        $data['name'] = $this->normalizeName((string) ($data['name'] ?? ''));

        $model = CarModel::query()->createOrFirst(
            [
                'name'     => $data['name'],
                'brand_id' => $data['brand_id'],
            ],
            $data
        )->loadMissing('brand');

        // write-through
        Cache::tags($this->tagModel((int) $model->id))
            ->put($this->keyById((int) $model->id), $model, self::TTL_SECONDS);

        Cache::tags($this->tagName((string) $model->name))
            ->put($this->keyByName((string) $model->name), $model, self::TTL_SECONDS);

        // invalidate affected lists
        Cache::tags($this->tagModels())->forget($this->keyAll());
        Cache::tags($this->tagBrand((int) $model->brand_id))->flush();

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
        $model->refresh()->loadMissing('brand');

        Cache::tags($this->tagModel($model->id))
            ->put($this->keyById($model->id), $model, self::TTL_SECONDS);

        Cache::tags($this->tagName($model->name))
            ->put($this->keyByName($model->name), $model, self::TTL_SECONDS);

        // invalidate lists
        Cache::tags($this->tagModels())->forget($this->keyAll());
        Cache::tags($this->tagBrand($oldBrandId))->flush();
        Cache::tags($this->tagBrand($model->brand_id))->flush();

        // if name changed, clear old name scope
        if ($oldName !== $model->name) {
            Cache::tags($this->tagName($oldName))->flush();
        }
    }

    /** @inheritDoc */
    public function delete(CarModel $model): bool
    {
        $id = $model->id;
        $brandId = $model->brand_id;
        $name = $model->name;

        $ok = (bool) $model->delete();

        Cache::tags($this->tagModel($id))->flush();
        Cache::tags($this->tagBrand($brandId))->flush();
        Cache::tags($this->tagName($name))->flush();

        Cache::tags($this->tagModels())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findByName(string $name): ?CarModel
    {
        /** @var CarModel|null $model */
        $model = Cache::tags($this->tagName($name))
            ->remember($this->keyByName($name), self::TTL_SECONDS, function () use ($name) {
                $normalized = $this->normalizeName($name);

                return CarModel::query()
                    ->with('brand')
                    ->whereRaw('lower(name) = ?', [$normalized])
                    ->first();
            });

        // warm id cache
        if ($model) {
            Cache::tags($this->tagModel($model->id))
                ->put($this->keyById($model->id), $model, self::TTL_SECONDS);
        }

        return $model;
    }

    /** @inheritDoc */
    public function findByBrand(int $brandId): Collection
    {
        /** @var Collection<int,CarModel> $models */
        $models = Cache::tags($this->tagBrand($brandId))
            ->remember($this->keyByBrand($brandId), self::TTL_SECONDS, function () use ($brandId) {
                return CarModel::query()
                    ->with('brand')
                    ->where('brand_id', $brandId)
                    ->orderBy('name')
                    ->get();
            });

        // Optional: warm per-id + per-name caches
        foreach ($models as $m) {
            Cache::tags($this->tagModel($m->id))
                ->put($this->keyById($m->id), $m, self::TTL_SECONDS);

            Cache::tags($this->tagName($m->name))
                ->put($this->keyByName($m->name), $m, self::TTL_SECONDS);
        }

        return $models;
    }
}

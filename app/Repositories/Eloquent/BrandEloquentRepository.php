<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of BrandRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 */
readonly class BrandEloquentRepository implements BrandRepositoryInterface
{
    /**
     * Create a new brand repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Brand> $brands */
        $brands = $this->cache->rememberBrandsAll(function () {
            return Brand::query()
                ->orderBy('name')
                ->get();
        });

        foreach ($brands as $brand) {
            $this->cache->putBrand($brand);
        }

        return $brands;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = $this->cache->rememberBrandById($id, fn () => Brand::query()->find($id));

        return $brand;
    }

    /** @inheritDoc */
    public function createOrFirst(string $name): Brand
    {
        $name = mb_strtolower(trim($name));

        $brand = Brand::query()->createOrFirst(
            ['name' => $name],
            ['name' => $name]
        );

        $this->cache->putBrand($brand);
        $this->cache->forgetBrandsAll();

        return $brand;
    }

    /** @inheritDoc */
    public function delete(Brand $brand): void
    {
        $id = $brand->id;

        $brand->delete();

        $this->cache->forgetBrand($id);
        $this->cache->forgetBrandsAll();
        $this->cache->forgetModelsByBrand($id);
        $this->cache->invalidateCarsAndPersonsByBrandId($id);
    }

    /** @inheritDoc */
    public function create(array $attributes): Brand
    {
        $brand = Brand::query()->create($attributes);

        $this->cache->putBrand($brand);
        $this->cache->forgetBrandsAll();

        return $brand;
    }

    /** @inheritDoc */
    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->update($attributes);
        $brand->refresh();

        $this->cache->putBrand($brand);
        $this->cache->forgetBrandsAll();

        return $brand;
    }

    /** @inheritDoc */
    public function paginateForAdmin(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Brand::query()->paginate($perPage);
    }

    /** @inheritDoc */
    public function hasModels(Brand $brand): bool
    {
        return $brand->models()->exists();
    }
}

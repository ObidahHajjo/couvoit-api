<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of BrandRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * @author Covoiturage API
 *
 * @description Repository for managing Brand entities with caching support.
 */
readonly class BrandEloquentRepository implements BrandRepositoryInterface
{
    /**
     * Create a new brand repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Get all brands ordered by name.
     *
     * @return Collection<int, Brand> Collection of all Brand instances
     */
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

    /**
     * Find a brand by its ID.
     *
     * @param  int  $id  The brand ID to find
     * @return Brand|null The Brand instance if found, null otherwise
     */
    public function findById(int $id): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = $this->cache->rememberBrandById($id, fn () => Brand::query()->find($id));

        return $brand;
    }

    /**
     * Create a new brand or return existing one by name.
     *
     * @param  string  $name  The brand name (will be normalized to lowercase)
     * @return Brand The created or existing Brand instance
     */
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

    /**
     * Delete a brand.
     *
     * @param  Brand  $brand  The Brand instance to delete
     *
     * @throws \Exception When database deletion fails
     */
    public function delete(Brand $brand): void
    {
        $id = $brand->id;

        $brand->delete();

        $this->cache->forgetBrand($id);
        $this->cache->forgetBrandsAll();
        $this->cache->forgetModelsByBrand($id);
        $this->cache->invalidateCarsAndPersonsByBrandId($id);
    }

    /**
     * Create a new brand.
     *
     * @param  array<string, mixed>  $attributes  Brand attributes to create
     * @return Brand The newly created Brand instance
     *
     * @throws QueryException When creation fails
     */
    public function create(array $attributes): Brand
    {
        $brand = Brand::query()->create($attributes);

        $this->cache->putBrand($brand);
        $this->cache->forgetBrandsAll();

        return $brand;
    }

    /**
     * Update a brand with new attributes.
     *
     * @param  Brand  $brand  The Brand instance to update
     * @param  array<string, mixed>  $attributes  New attributes to apply
     * @return Brand The updated Brand instance
     */
    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->update($attributes);
        $brand->refresh();

        $this->cache->putBrand($brand);
        $this->cache->forgetBrandsAll();

        return $brand;
    }

    /**
     * Paginate all brands for admin panel.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of Brand instances
     */
    public function paginateForAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return Brand::query()->paginate($perPage);
    }

    /**
     * Check if a brand has associated models.
     *
     * @param  Brand  $brand  The Brand instance to check
     * @return bool True if brand has models, false otherwise
     */
    public function hasModels(Brand $brand): bool
    {
        return $brand->models()->exists();
    }
}

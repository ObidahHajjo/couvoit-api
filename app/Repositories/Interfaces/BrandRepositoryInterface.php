<?php

namespace App\Repositories\Interfaces;

use App\Models\Brand;
use Illuminate\Support\Collection;

/**
 * Interface BrandRepositoryInterface
 *
 * Persistence contract for Brand entity.
 * Responsible only for database access operations.
 */
interface BrandRepositoryInterface
{
    /**
     * Retrieve all brands.
     *
     * @return Collection<int, Brand> Collection of Brand models.
     */
    public function all(): Collection;

    /**
     * Find a brand by its identifier.
     *
     * @param int $id Brand primary key.
     * @return Brand|null The Brand model if found, otherwise null.
     */
    public function findById(int $id): ?Brand;

    /**
     * Retrieve an existing brand by name or create it if it does not exist.
     *
     * Name comparison should be handled consistently
     * (e.g., lowercase normalization before persistence).
     *
     * @param string $name Brand name.
     * @return Brand The existing or newly created Brand model.
     */
    public function createOrFirst(string $name): Brand;

    /**
     * Delete a brand.
     *
     * The Brand instance is expected to be a valid
     * Eloquent model (usually provided via route model binding).
     *
     * @param Brand $brand Brand instance to delete.
     * @return void
     */
    public function delete(Brand $brand): void;
}

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
     * Retrieve all brands ordered by name.
     *
     * Results are cached and individual brand caches are warmed.
     *
     * @return Collection<int,Brand>
     */
    public function all(): Collection;

    /**
     * Find a brand by its identifier.
     *
     * @param int $id
     * @return Brand|null
     */
    public function findById(int $id): ?Brand;

    /**
     * Create a brand if it does not exist, or return the existing one.
     *
     * Cache is updated for the specific brand and
     * the global list cache is invalidated.
     *
     * @param string $name
     * @return Brand
     */
    public function createOrFirst(string $name): Brand;

    /**
     * Delete a brand and invalidate related cache entries.
     *
     * @param Brand $brand
     * @return void
     */
    public function delete(Brand $brand): void;
}

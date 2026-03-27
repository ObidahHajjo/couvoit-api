<?php

namespace App\Services\Interfaces;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for admin brand management services.
 */
interface AdminBrandServiceInterface
{
    /**
     * List all brands with pagination.
     *
     * @param  int  $perPage  Number of items per page.
     * @return LengthAwarePaginator<int, Brand>
     */
    public function listBrands(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new brand.
     *
     * @param  array<string, mixed>  $data  Brand creation data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function createBrand(array $data): Brand;

    /**
     * Update an existing brand.
     *
     * @param  Brand  $brand  Brand to update.
     * @param  array<string, mixed>  $data  Brand update data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function updateBrand(Brand $brand, array $data): Brand;

    /**
     * Delete a brand.
     *
     * @param  Brand  $brand  Brand to delete.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteBrand(Brand $brand): void;
}

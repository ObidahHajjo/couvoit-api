<?php

/**
 * @author Admin
 *
 * @description Service implementation for managing brand operations in the admin panel.
 */

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Services\Interfaces\AdminBrandServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service implementation for admin brand management operations.
 */
readonly class AdminBrandService implements AdminBrandServiceInterface
{
    public function __construct(
        private BrandRepositoryInterface $brands,
    ) {}

    /**
     * List all brands with pagination.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of brands
     */
    public function listBrands(int $perPage = 15): LengthAwarePaginator
    {
        return $this->brands->paginateForAdmin($perPage);
    }

    /**
     * Create a new brand.
     *
     * @param  array  $data  Brand data including name and other attributes
     * @return Brand The created brand instance
     */
    public function createBrand(array $data): Brand
    {
        return $this->brands->create($data);
    }

    /**
     * Update an existing brand.
     *
     * @param  Brand  $brand  The brand to update
     * @param  array  $data  Updated brand data
     * @return Brand The updated brand instance
     */
    public function updateBrand(Brand $brand, array $data): Brand
    {
        return $this->brands->update($brand, $data);
    }

    /**
     * Delete a brand.
     *
     * @param  Brand  $brand  The brand to delete
     *
     * @throws ConflictException When the brand has associated models
     */
    public function deleteBrand(Brand $brand): void
    {
        if ($this->brands->hasModels($brand)) {
            throw new ConflictException('Cannot delete brand with existing models.');
        }

        $this->brands->delete($brand);
    }
}

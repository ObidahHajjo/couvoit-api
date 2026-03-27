<?php

declare(strict_types=1);

/**
 * @author Covoiturage Team
 *
 * @description Default implementation of brand application workflows.
 */

namespace App\Services\Implementations;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Services\Interfaces\BrandServiceInterface;
use Illuminate\Support\Collection;

/**
 * @description Handles brand retrieval and listing operations.
 */
readonly class BrandService implements BrandServiceInterface
{
    /**
     * Create a new brand service instance.
     */
    public function __construct(
        private BrandRepositoryInterface $brands
    ) {}

    /**
     * @return Collection<int, Brand>
     */
    public function getBrands(): Collection
    {
        return $this->brands->all();
    }

    /**
     * @param  Brand  $brand  The brand instance to retrieve
     */
    public function getBrand(Brand $brand): Brand
    {
        return $brand;
    }
}

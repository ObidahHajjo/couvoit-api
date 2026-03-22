<?php

namespace App\Services\Implementations;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Services\Interfaces\BrandServiceInterface;
use Illuminate\Support\Collection;

/**
 * Default implementation of brand application workflows.
 */
readonly class BrandService implements BrandServiceInterface
{
    public function __construct(
        private BrandRepositoryInterface $brands
    ) {}

    /** {@inheritDoc} */
    public function getBrands(): Collection
    {
        return $this->brands->all();
    }

    /** {@inheritDoc} */
    public function getBrand(Brand $brand): Brand
    {
        return $brand;
    }
}

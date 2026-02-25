<?php

namespace App\Services\Interfaces;

use App\Models\Brand;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Contract for brand-related application services.
 */
interface BrandServiceInterface
{
    /**
     * Retrieve all brands.
     *
     * @return Collection<int, Brand>
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function getBrands(): Collection;

    /**
     * Retrieve a specific brand.
     *
     * @param Brand $brand
     *
     * @return Brand
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function getBrand(Brand $brand): Brand;
}

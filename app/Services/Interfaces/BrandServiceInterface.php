<?php

namespace App\Services\Interfaces;

use App\Models\Brand;
use Illuminate\Support\Collection;

interface BrandServiceInterface
{
    /**
     * Retrieve all brands.
     *
     * @return Collection<int, Brand>
     */
    public function getBrands(): Collection;

    /**
     * Retrieve a brand by its ID.
     *
     * @param Brand $brand
     * @return Brand
     */
    public function getBrand(Brand $brand): Brand;
}

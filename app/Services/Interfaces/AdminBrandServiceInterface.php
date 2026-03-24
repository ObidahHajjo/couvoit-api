<?php

namespace App\Services\Interfaces;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdminBrandServiceInterface
{
    public function listBrands(int $perPage = 15): LengthAwarePaginator;

    public function createBrand(array $data): Brand;

    public function updateBrand(Brand $brand, array $data): Brand;

    public function deleteBrand(Brand $brand): void;
}

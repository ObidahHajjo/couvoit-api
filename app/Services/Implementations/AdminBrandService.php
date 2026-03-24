<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Services\Interfaces\AdminBrandServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class AdminBrandService implements AdminBrandServiceInterface
{
    public function __construct(
        private BrandRepositoryInterface $brands,
    ) {}

    public function listBrands(int $perPage = 15): LengthAwarePaginator
    {
        return $this->brands->paginateForAdmin($perPage);
    }

    public function createBrand(array $data): Brand
    {
        return $this->brands->create($data);
    }

    public function updateBrand(Brand $brand, array $data): Brand
    {
        return $this->brands->update($brand, $data);
    }

    public function deleteBrand(Brand $brand): void
    {
        if ($this->brands->hasModels($brand)) {
            throw new ConflictException('Cannot delete brand with existing models.');
        }

        $this->brands->delete($brand);
    }
}

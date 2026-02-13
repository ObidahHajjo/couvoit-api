<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Support\Collection;

class BrandEloquentRepository implements BrandRepositoryInterface
{
    /** {@inheritDoc} */
    public function all(): Collection
    {
        return Brand::query()->orderBy('name')->get();
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Brand
    {
        return Brand::query()->find($id);
    }

    /** {@inheritDoc} */
    public function createOrFirst(string $name): Brand
    {
        $name = mb_strtolower(trim($name));

        return Brand::query()->firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );
    }

    /** {@inheritDoc} */
    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}

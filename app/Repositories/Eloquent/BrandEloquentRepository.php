<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BrandEloquentRepository implements BrandRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function keyAll(): string { return 'brands:all'; }
    private function keyById(int $id): string { return "brands:$id"; }

    /** {@inheritDoc} */
    public function all(): Collection
    {
        /** @var Collection $cached */
        return  Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return Brand::query()->orderBy('name')->get();
        });
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Brand
    {
        return Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Brand::query()->find($id);
        });
    }

    /** {@inheritDoc} */
    public function createOrFirst(string $name): Brand
    {
        $name = mb_strtolower(trim($name));

        $brand = Brand::query()->createOrFirst(
            ['name' => $name],
            ['name' => $name]
        );

        // write-through cache update
        Cache::put($this->keyById((int) $brand->id), $brand, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $brand;
    }

    /** {@inheritDoc} */
    public function delete(Brand $brand): void
    {
        $id = $brand->id;
        $brand->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyAll());
    }
}

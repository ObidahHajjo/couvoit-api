<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of BrandRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 */
class BrandEloquentRepository implements BrandRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    /**
     * @return array<int,string>
     */
    private function tagBrands(): array
    {
        return ['brands'];
    }

    /**
     * @param int $id
     * @return array<int,string>
     */
    private function tagBrand(int $id): array
    {
        return ['brands', "brand:$id"];
    }

    private function keyAll(): string
    {
        return 'brands:all';
    }

    private function keyById(int $id): string
    {
        return "brands:$id";
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Brand> $brands */
        $brands = Cache::tags($this->tagBrands())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Brand::query()
                    ->orderBy('name')
                    ->get();
            });

        // Warm per-brand cache entries
        foreach ($brands as $brand) {
            Cache::tags($this->tagBrand($brand->id))
                ->put($this->keyById($brand->id), $brand, self::TTL_SECONDS);
        }

        return $brands;
    }

   /** @inheritDoc */
    public function findById(int $id): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = Cache::tags($this->tagBrand($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Brand::query()->find($id);
            });

        return $brand;
    }

    /** @inheritDoc */
    public function createOrFirst(string $name): Brand
    {
        $name = mb_strtolower(trim($name));

        $brand = Brand::query()->createOrFirst(
            ['name' => $name],
            ['name' => $name]
        );

        Cache::tags($this->tagBrand((int) $brand->id))
            ->put($this->keyById((int) $brand->id), $brand, self::TTL_SECONDS);

        Cache::tags($this->tagBrands())
            ->forget($this->keyAll());

        return $brand;
    }

    /** @inheritDoc */
    public function delete(Brand $brand): void
    {
        $id = $brand->id;

        $brand->delete();

        Cache::tags($this->tagBrand($id))->flush();
        Cache::tags($this->tagBrands())->forget($this->keyAll());
    }
}

<?php

namespace Tests\Unit\Repositories;

use App\Models\Brand;
use App\Repositories\Eloquent\BrandEloquentRepository;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BrandEloquentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_returns_collection_and_warms_per_brand_cache(): void
    {
        $b1 = Brand::query()->create(['name' => 'audi']);
        $b2 = Brand::query()->create(['name' => 'bmw']);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new BrandEloquentRepository($cache);

        $collection = collect([$b1, $b2]);

        $cache->shouldReceive('rememberBrandsAll')
            ->once()
            ->andReturnUsing(function ($callback) use ($collection) {
                return $collection;
            });

        $cache->shouldReceive('putBrand')
            ->once()
            ->with(Mockery::type(Brand::class));

        $cache->shouldReceive('putBrand')
            ->once()
            ->with(Mockery::type(Brand::class));

        $res = $repo->all();

        $this->assertCount(2, $res);
        $this->assertSame('audi', $res[0]->name);
        $this->assertSame('bmw', $res[1]->name);
    }

    public function test_find_by_id_returns_brand(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new BrandEloquentRepository($cache);

        $cache->shouldReceive('rememberBrandById')
            ->once()
            ->with($brand->id, Mockery::type('callable'))
            ->andReturnUsing(function ($id, $callback) {
                return $callback();
            });

        $res = $repo->findById($brand->id);

        $this->assertInstanceOf(Brand::class, $res);
        $this->assertSame($brand->id, $res->id);
        $this->assertSame('toyota', $res->name);
    }

    public function test_create_or_first_normalizes_and_updates_cache(): void
    {
        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new BrandEloquentRepository($cache);

        $cache->shouldReceive('putBrand')
            ->once()
            ->with(Mockery::type(Brand::class));

        $cache->shouldReceive('forgetBrandsAll')
            ->once();

        $brand = $repo->createOrFirst(' ToYoTa ');

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertSame('toyota', $brand->name);
    }

    public function test_delete_flushes_cache_and_forgets_all_key(): void
    {
        $brand = Brand::query()->create(['name' => 'ford']);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new BrandEloquentRepository($cache);

        $cache->shouldReceive('forgetBrand')
            ->once()
            ->with($brand->id);

        $cache->shouldReceive('forgetBrandsAll')
            ->once();

        $cache->shouldReceive('forgetModelsByBrand')
            ->once()
            ->with($brand->id);

        $cache->shouldReceive('invalidateCarsAndPersonsByBrandId')
            ->once()
            ->with($brand->id);

        $repo->delete($brand);

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}

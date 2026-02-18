<?php

namespace Tests\Unit\Repositories;

use App\Models\Brand;
use App\Repositories\Eloquent\BrandEloquentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Throwable;

/**
 * Class BrandEloquentRepositoryTest
 *
 * Unit-style repository tests using Cache facade mocking because:
 * - Cache tags are not supported on all testing cache stores.
 *
 * Covered:
 * - all(): returns ordered brands via remember closure and warms per-brand cache (put)
 * - findById(): returns brand via remember closure
 * - createOrFirst(): normalizes name, creates or first, updates caches, forgets list key
 * - delete(): deletes, flushes per-brand tag, forgets list key
 */
class BrandEloquentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Repository under test.
     *
     * @var BrandEloquentRepository
     */
    private BrandEloquentRepository $repo;

    /**
     * Setup repository.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BrandEloquentRepository();
    }

    /**
     * all() should return brands and warm per-brand caches.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_all_returns_collection_and_warms_per_brand_cache(): void
    {
        $b1 = Brand::query()->create(['name' => 'audi']);
        $b2 = Brand::query()->create(['name' => 'bmw']);

        // Mock Cache::tags()->remember() to execute closure (cache miss simulation)
        $tagsBrands = Mockery::mock();
        $tagsBrand1 = Mockery::mock();
        $tagsBrand2 = Mockery::mock();

        Cache::shouldReceive('tags')->with(['brands'])->andReturn($tagsBrands);

        $tagsBrands->shouldReceive('remember')
            ->once()
            ->with('brands:all', Mockery::type('int'), Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        // Warming per-brand caches => Cache::tags(['brands', "brand:{id}"])->put(...)
        Cache::shouldReceive('tags')->with(['brands', "brand:$b1->id"])->andReturn($tagsBrand1);
        Cache::shouldReceive('tags')->with(['brands', "brand:$b2->id"])->andReturn($tagsBrand2);

        $tagsBrand1->shouldReceive('put')->once()->with("brands:$b1->id", Mockery::type(Brand::class), Mockery::type('int'));
        $tagsBrand2->shouldReceive('put')->once()->with("brands:$b2->id", Mockery::type(Brand::class), Mockery::type('int'));

        $res = $this->repo->all();

        // Repository orders by name; ensure first is audi then bmw
        $this->assertCount(2, $res);
        $this->assertSame('audi', $res[0]->name);
        $this->assertSame('bmw', $res[1]->name);
    }

    /**
     * findById() should return a brand via remember closure.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_find_by_id_returns_brand(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);

        $tags = Mockery::mock();
        Cache::shouldReceive('tags')->with(['brands', "brand:$brand->id"])->andReturn($tags);

        $tags->shouldReceive('remember')
            ->once()
            ->with("brands:$brand->id", Mockery::type('int'), Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        $res = $this->repo->findById($brand->id);

        $this->assertInstanceOf(Brand::class, $res);
        $this->assertSame($brand->id, $res->id);
        $this->assertSame('toyota', $res->name);
    }

    /**
     * createOrFirst() should normalize name, put per-brand cache and forget list cache.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_create_or_first_normalizes_and_updates_cache(): void
    {
        $brandTagMock = \Mockery::mock();
        $brandsTagMock = \Mockery::mock();

        // Cache::tags(...) routing based on tag list
        Cache::shouldReceive('tags')
            ->andReturnUsing(function (array $tags) use ($brandTagMock, $brandsTagMock) {
                // ['brands']
                if ($tags === ['brands']) {
                    return $brandsTagMock;
                }

                // ['brands', 'brand:{id}']
                if (count($tags) === 2 && $tags[0] === 'brands' && str_starts_with((string) $tags[1], 'brand:')) {
                    return $brandTagMock;
                }

                return \Mockery::mock();
            });

        // Expect per-brand cache write
        $brandTagMock->shouldReceive('put')
            ->once()
            ->with(
                \Mockery::on(fn(string $key) => str_starts_with($key, 'brands:')),
                \Mockery::type(Brand::class),
                \Mockery::type('int')
            )
            ->andReturnNull();

        // Expect list cache invalidation
        $brandsTagMock->shouldReceive('forget')
            ->once()
            ->with('brands:all')
            ->andReturnTrue();

        $brand = $this->repo->createOrFirst('  ToYoTa  ');

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertSame('toyota', $brand->name);
    }

    /**
     * delete() should delete the brand, flush per-brand tag and forget list cache.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_delete_flushes_cache_and_forgets_all_key(): void
    {
        $brand = Brand::query()->create(['name' => 'ford']);

        $tagsBrand = Mockery::mock();
        $tagsBrands = Mockery::mock();

        Cache::shouldReceive('tags')->with(['brands', "brand:$brand->id"])->andReturn($tagsBrand);
        Cache::shouldReceive('tags')->with(['brands'])->andReturn($tagsBrands);

        $tagsBrand->shouldReceive('flush')->once();
        $tagsBrands->shouldReceive('forget')->once()->with('brands:all');

        $this->repo->delete($brand);

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}

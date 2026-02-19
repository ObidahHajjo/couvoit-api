<?php

namespace Tests\Unit\Services;

use App\Models\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Services\Implementations\BrandService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

/**
 * Class BrandServiceTest
 *
 * Unit tests for BrandService:
 * - getBrands() delegates to repository->all()
 * - getBrand() returns the given Brand instance
 */
class BrandServiceTest extends TestCase
{
    /**
     * Mocked repository dependency.
     *
     * @var BrandRepositoryInterface&MockInterface
     */
    private BrandRepositoryInterface $repo;

    /**
     * Service under test.
     *
     * @var BrandService
     */
    private BrandService $service;

    /**
     * Setup service + repository mock.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var BrandRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(BrandRepositoryInterface::class);
        $this->repo = $repo;

        $this->service = new BrandService($this->repo);
    }

    /**
     * getBrands() should delegate to repository->all().
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_get_brands_delegates_to_repository_all(): void
    {
        $collection = new Collection([
            new Brand(['name' => 'audi']),
            new Brand(['name' => 'bmw']),
        ]);

        $this->repo->shouldReceive('all')
            ->once()
            ->andReturn($collection);

        $res = $this->service->getBrands();

        $this->assertSame($collection, $res);
        $this->assertCount(2, $res);
    }

    /**
     * getBrand() should return the exact Brand instance passed to it.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_get_brand_returns_same_instance(): void
    {
        $brand = new Brand(['name' => 'toyota']);

        $res = $this->service->getBrand($brand);

        $this->assertSame($brand, $res);
    }
}

<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\BrandController;
use App\Models\Brand;
use App\Services\Interfaces\BrandServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Throwable;

/**
 * Class BrandControllerTest
 *
 * Feature tests for BrandController:
 * - GET /brands returns BrandResource collection under data.*
 * - GET /brands/{brand} returns BrandResource under data.*
 */
class BrandControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route URI for listing brands.
     *
     * @var string
     */
    private string $indexUri = '/brands';

    /**
     * Setup routes for this test class.
     * (We register local routes to ensure the test is deterministic.)
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class])->group(function () {
            Route::get('/brands', [BrandController::class, 'index']);
            Route::get('/brands/{brand}', [BrandController::class, 'show']);
        });
    }

    /**
     * GET /brands should return 200 and a data array of brands.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_index_returns_ok_with_brand_collection_resource(): void
    {
        $b1 = Brand::query()->create(['name' => 'audi']);
        $b2 = Brand::query()->create(['name' => 'bmw']);

        $this->mock(BrandServiceInterface::class, function ($mock) use ($b1, $b2) {
            $mock->shouldReceive('getBrands')
                ->once()
                ->andReturn(new Collection([$b1, $b2]));
        });

        $res = $this->getJson($this->indexUri);

        $res->assertOk();

        // BrandResource::collection(...) -> default wrapping => { "data": [ ... ] }
        $res->assertJsonCount(2, 'data');
        $res->assertJsonPath('data.0.id', $b1->id);
        $res->assertJsonPath('data.1.id', $b2->id);
    }

    /**
     * GET /brands/{brand} should return 200 and the brand payload.
     * Handles both wrapped and unwrapped JsonResource outputs.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_show_returns_ok_with_brand_resource(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);

        // Controller constructor requires BrandServiceInterface; show() does not call it.
        $this->mock(BrandServiceInterface::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        $res = $this->getJson("/brands/$brand->id");

        $res->assertOk();

        // Assert the name exists in payload regardless of wrapping
        $res->assertJsonFragment(['name' => $brand->name]);

        // Assert the id exists either wrapped (data.id) or unwrapped (id)
        $json = $res->json();

        $wrappedId = data_get($json, 'data.id');
        $rootId = data_get($json, 'id');

        $this->assertTrue(
            ((int) $wrappedId === (int) $brand->id) || ((int) $rootId === (int) $brand->id),
            'Expected brand id to be present either at data.id or id.'
        );
    }
}

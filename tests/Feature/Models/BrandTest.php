<?php

namespace Tests\Feature\Models;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Brand)->timestamps);
    }

    public function test_brand_fillable_allows_mass_assignment(): void
    {
        $brand = Brand::query()->create(['name' => 'Toyota']);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Toyota',
        ]);
    }

    public function test_brand_has_many_models(): void
    {
        $brand = Brand::query()->create(['name' => 'Renault']);
        $type = Type::query()->create(['type' => 'Hatchback']);

        $m1 = CarModel::query()->create(['name' => 'Clio', 'brand_id' => $brand->id, 'type_id' => $type->id]);
        $m2 = CarModel::query()->create(['name' => 'Megane', 'brand_id' => $brand->id, 'type_id' => $type->id]);

        $brand->refresh();

        $this->assertCount(2, $brand->models);
        $this->assertTrue($brand->models->contains($m1));
        $this->assertTrue($brand->models->contains($m2));
    }
}

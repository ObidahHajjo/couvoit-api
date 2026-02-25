<?php

namespace Tests\Feature\Models;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_car_model_uses_models_table(): void
    {
        $this->assertSame('models', (new CarModel())->getTable());
    }

    public function test_car_model_has_timestamps_disabled(): void
    {
        $this->assertFalse((new CarModel())->timestamps);
    }

    public function test_car_model_belongs_to_brand_and_type(): void
    {
        $brand = Brand::query()->create(['name' => 'Tesla']);
        $type = Type::query()->create(['type' => 'EV']);

        $model = CarModel::query()->create([
            'name' => 'Model 3',
            'seats' => 5,
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $this->assertSame($brand->id, $model->brand->id);
        $this->assertSame('Tesla', $model->brand->name);

        $this->assertSame($type->id, $model->type->id);
        $this->assertSame('EV', $model->type->type);
    }
}

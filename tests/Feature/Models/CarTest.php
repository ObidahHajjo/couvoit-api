<?php

namespace Tests\Feature\Models;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarTest extends TestCase
{
    use RefreshDatabase;

    private function seedModelAndColor(): array
    {
        $brand = Brand::query()->create(['name' => 'BMW']);
        $type = Type::query()->create(['type' => 'Sedan']);
        $model = CarModel::query()->create([
            'name' => 'Series 3',
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);
        $color = Color::query()->create(['name' => 'Blue', 'hex_code' => '#0000FF']);

        return [$model, $color];
    }

    public function test_car_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Car)->timestamps);
    }

    public function test_car_fillable_allows_mass_assignment(): void
    {
        [$model, $color] = $this->seedModelAndColor();

        $car = Car::query()->create([
            'license_plate' => 'AA-123-AA',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'license_plate' => 'AA-123-AA',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);
    }

    public function test_car_belongs_to_model_and_color(): void
    {
        [$model, $color] = $this->seedModelAndColor();

        $car = Car::query()->create([
            'license_plate' => 'BB-456-BB',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);

        $this->assertSame($model->id, $car->model->id);
        $this->assertSame($color->id, $car->color->id);
        $this->assertSame(5, $car->seats);
        $this->assertSame('Series 3', $car->model->name);
        $this->assertSame('#0000FF', $car->color->hex_code);
    }
}

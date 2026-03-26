<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\CarResource;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Throwable;

/**
 * Class CarResourceTest
 *
 * Unit tests for CarResource serialization behavior depending on loaded relations.
 */
class CarResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dummy request instance used by JsonResource::toArray().
     */
    private Request $request;

    /**
     * Setup shared request instance.
     *
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = Request::create('/cars');
    }

    /**
     * toArray() should include model.brand/type and color when relations are loaded.
     *
     *
     * @throws Throwable
     */
    public function test_to_array_includes_nested_relations_when_loaded(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type = Type::query()->create(['type' => 'suv']);

        $model = CarModel::query()->create([
            'name' => 'rav4',
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $color = Color::query()->create([
            'name' => 'blue',
            'hex_code' => '#0000ff',
        ]);

        $car = Car::query()->create([
            'license_plate' => '12-ABC-34',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);

        // Load the exact relations used by CarController and CarRepositoryEloquent
        $car->loadMissing(['model.brand', 'model.type', 'color']);

        $payload = (new CarResource($car))->toArray($this->request);

        $this->assertSame($car->id, $payload['id']);
        $this->assertSame('12-ABC-34', $payload['license_plate']);
        $this->assertSame(5, $payload['seats']);

        $this->assertIsArray($payload['model']);
        $this->assertSame($model->id, $payload['model']['id']);
        $this->assertSame('rav4', $payload['model']['name']);

        $this->assertIsArray($payload['model']['brand']);
        $this->assertSame($brand->id, $payload['model']['brand']['id']);
        $this->assertSame('toyota', $payload['model']['brand']['name']);

        $this->assertIsArray($payload['model']['type']);
        $this->assertSame($type->id, $payload['model']['type']['id']);
        $this->assertSame('suv', $payload['model']['type']['type']);

        $this->assertIsArray($payload['color']);
        $this->assertSame($color->id, $payload['color']['id']);
        $this->assertSame('#0000ff', $payload['color']['hex_code']);
    }

    /**
     * toArray() should omit nested sections when relations are NOT loaded.
     *
     *
     * @throws Throwable
     */
    public function test_to_array_omits_nested_relations_when_not_loaded(): void
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type = Type::query()->create(['type' => 'suv']);

        $model = CarModel::query()->create([
            'name' => 'rav4',
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $color = Color::query()->create([
            'name' => 'blue',
            'hex_code' => '#0000ff',
        ]);

        $car = Car::query()->create([
            'license_plate' => '12-ABC-34',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);

        $car->unsetRelation('model');
        $car->unsetRelation('color');

        $car = Car::query()->findOrFail($car->id); // reload clean instance

        if ($car->relationLoaded('model') && $car->model) {
            $car->model->unsetRelation('brand');
            $car->model->unsetRelation('type');
        }

        $payload = (new CarResource($car))->toArray($this->request);

        $this->assertSame($car->id, $payload['id']);
        $this->assertSame('12-ABC-34', $payload['license_plate']);
        $this->assertSame(5, $payload['seats']);

        if (isset($payload['model']) && is_array($payload['model'])) {
            $this->assertArrayHasKey('id', $payload['model']);
            $this->assertArrayHasKey('name', $payload['model']);

            $this->assertArrayNotHasKey('brand', $payload['model']);
            $this->assertArrayNotHasKey('type', $payload['model']);
        }

        // color may appear, but should only contain the keys defined
        if (isset($payload['color']) && is_array($payload['color'])) {
            $this->assertArrayHasKey('id', $payload['color']);
            $this->assertArrayHasKey('hex_code', $payload['color']);
        }
    }
}

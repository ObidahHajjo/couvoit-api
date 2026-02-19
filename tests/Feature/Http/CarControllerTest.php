<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\CarController;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Person;
use App\Models\Type;
use App\Services\Interfaces\CarServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Throwable;

/**
 * Class CarControllerTest
 *
 * Feature tests for CarController endpoints with authorization mocked via Gate.
 */
class CarControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup deterministic test routes with implicit binding enabled.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class])->group(function () {
            Route::get('/cars', [CarController::class, 'index']);
            Route::get('/cars/{car}', [CarController::class, 'show']);
        });
    }

    /**
     * Seed roles with stable IDs (1=user, 2=admin).
     *
     * @return void
     *
     * @throws Throwable
     */
    private function seedRoles(): void
    {
        // Role model has guarded id in your project; insert via query and accept auto ids,
        // but your Person::ROLE_* constants assume 1/2, so set explicitly using DB is best.
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'user'],
            ['id' => 2, 'name' => 'admin'],
        ]);
    }

    /**
     * Create a full car graph used by CarResource.
     *
     * @return Car
     *
     * @throws Throwable
     */
    private function makeCarGraph(): Car
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type  = Type::query()->create(['type' => 'suv']);
        $model = CarModel::query()->create([
            'name' => 'rav4',
            'seats' => 5,
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);
        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);

        return Car::query()->create([
            'license_plate' => 'AA-123-BB',
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);
    }

    /**
     * index() for a normal user should return only his car (or empty list).
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_index_user_returns_only_own_car(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $person = Person::query()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000111',
            'email' => 'u@example.com',
            'pseudo' => 'user1',
            'role_id' => 1,
            'is_active' => true,
            'car_id' => $car->id,
        ]);

        // Allow authorize('viewAny', Car::class)
        Gate::shouldReceive('authorize')->andReturnTrue();

        // CarService not used for non-admin index
        $this->mock(CarServiceInterface::class, fn($m) => $m->shouldIgnoreMissing());

        $res = $this->actingAs($person)->getJson('/cars');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $car->id);
        $res->assertJsonPath('data.0.license_plate', 'AA-123-BB');
    }

    /**
     * index() for admin should delegate to service->getCars().
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_index_admin_delegates_to_service(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $admin = Person::query()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000222',
            'email' => 'a@example.com',
            'pseudo' => 'admin1',
            'role_id' => 2,
            'is_active' => true,
            'car_id' => null,
        ]);

        Gate::shouldReceive('authorize')->andReturnTrue();

        $this->mock(CarServiceInterface::class, function ($mock) use ($car) {
            $mock->shouldReceive('getCars')
                ->once()
                ->andReturn(new Collection([$car]));
        });

        $res = $this->actingAs($admin)->getJson('/cars');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $car->id);
    }

    /**
     * show() should return 200 and car payload when authorized.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_show_returns_ok_with_car_resource(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $person = Person::query()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000333',
            'email' => 'u2@example.com',
            'pseudo' => 'user2',
            'role_id' => 2, // make admin to avoid policy complexity
            'is_active' => true,
            'car_id' => null,
        ]);

        Gate::shouldReceive('authorize')->andReturnTrue();

        $this->mock(CarServiceInterface::class, fn($m) => $m->shouldIgnoreMissing());

        $res = $this->actingAs($person)->getJson("/cars/$car->id");

        $res->assertOk();
        $res->assertJsonPath('data.id', $car->id);
        $res->assertJsonPath('data.license_plate', 'AA-123-BB');
    }
}

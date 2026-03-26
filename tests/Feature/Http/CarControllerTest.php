<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\CarController;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Person;
use App\Models\Type;
use App\Models\User;
use App\Services\Interfaces\CarServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
     * @throws Throwable
     */
    private function seedRoles(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'user'],
            ['id' => 2, 'name' => 'admin'],
        ]);
    }

    /**
     * Create a full car graph used by CarResource.
     *
     * @throws Throwable
     */
    private function makeCarGraph(): Car
    {
        $brand = Brand::query()->create(['name' => 'toyota']);
        $type = Type::query()->create(['type' => 'suv']);
        $model = CarModel::query()->create([
            'name' => 'rav4',
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);
        $color = Color::query()->create(['name' => 'blue', 'hex_code' => '#0000ff']);

        return Car::query()->create([
            'license_plate' => '12-ABC-34',
            'seats' => 5,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);
    }

    /**
     * Create a Person profile (non-auth).
     *
     * @throws Throwable
     */
    private function makePersonProfile(array $overrides = []): Person
    {
        $suffix = Str::lower(Str::random(8));

        return Person::query()->create(array_merge([
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => "pseudo_$suffix",
            'phone' => '+336'.random_int(10000000, 99999999),
            'car_id' => null,
        ], $overrides));
    }

    /**
     * Create an auth User linked to a Person profile.
     *
     * @throws Throwable
     */
    private function makeUser(int $roleId, bool $isActive, Person $person, string $email): User
    {
        return User::query()->create([
            'email' => $email,
            'password' => Hash::make('secret12345'),
            'role_id' => $roleId,
            'is_active' => $isActive,
            'person_id' => $person->id,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_index_user_returns_only_own_car(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $person = $this->makePersonProfile(['car_id' => $car->id]);

        $user = $this->makeUser(
            roleId: 1,
            isActive: true,
            person: $person,
            email: 'u@example.com'
        );

        Gate::shouldReceive('authorize')->andReturnTrue();

        // CarService not used for non-admin index
        $this->mock(CarServiceInterface::class, fn ($m) => $m->shouldIgnoreMissing());

        $res = $this->actingAs($user)->getJson('/cars');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $car->id);
        $res->assertJsonPath('data.0.license_plate', '12-ABC-34');
    }

    /**
     * @throws Throwable
     */
    public function test_index_admin_delegates_to_service(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $person = $this->makePersonProfile(['car_id' => null]);

        $adminUser = $this->makeUser(
            roleId: 2,
            isActive: true,
            person: $person,
            email: 'a@example.com'
        );

        Gate::shouldReceive('authorize')->andReturnTrue();

        $this->mock(CarServiceInterface::class, function ($mock) use ($car) {
            $mock->shouldReceive('getCars')
                ->once()
                ->andReturn(new Collection([$car]));
        });

        $res = $this->actingAs($adminUser)->getJson('/cars');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $car->id);
    }

    /**
     * @throws Throwable
     */
    public function test_show_returns_ok_with_car_resource(): void
    {
        $this->seedRoles();

        $car = $this->makeCarGraph();

        $person = $this->makePersonProfile(['car_id' => null]);

        $adminUser = $this->makeUser(
            roleId: 2,
            isActive: true,
            person: $person,
            email: 'admin2@example.com'
        );

        Gate::shouldReceive('authorize')->andReturnTrue();

        $this->mock(CarServiceInterface::class, fn ($m) => $m->shouldIgnoreMissing());

        $res = $this->actingAs($adminUser)->getJson("/cars/$car->id");

        $res->assertOk();
        $res->assertJsonPath('data.id', $car->id);
        $res->assertJsonPath('data.license_plate', '12-ABC-34');
    }
}

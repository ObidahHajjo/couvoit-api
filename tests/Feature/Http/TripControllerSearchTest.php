<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\TripController;
use App\Models\Person;
use App\Models\User;
use App\Services\Interfaces\TripServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TripControllerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SubstituteBindings::class])->group(function () {
            Route::get('/trips-search-test', [TripController::class, 'index']);
        });
    }

    public function test_index_splits_tripdate_datetime_query_before_calling_service(): void
    {
        $user = $this->makeUser();
        $paginator = new LengthAwarePaginator([], 0, 15);

        $this->mock(TripServiceInterface::class, function ($mock) use ($paginator) {
            $mock->shouldReceive('searchTrips')
                ->once()
                ->with(null, null, '2026-03-26', '18:00', 15)
                ->andReturn($paginator);
        });

        $this->actingAs($user)->getJson('/trips-search-test?tripdate=2026-03-26%2018:00')
            ->assertOk();
    }

    private function makeUser(): User
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'user'],
            ['id' => 2, 'name' => 'admin'],
        ]);

        $person = Person::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'pseudo' => 'trip_search_user',
            'phone' => null,
            'car_id' => null,
        ]);

        return User::query()->create([
            'email' => 'trip-search@example.com',
            'password' => Hash::make('secret12345'),
            'role_id' => 1,
            'is_active' => true,
            'person_id' => $person->id,
        ]);
    }
}

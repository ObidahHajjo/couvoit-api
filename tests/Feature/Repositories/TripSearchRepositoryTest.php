<?php

namespace Tests\Feature\Repositories;

use App\Models\Address;
use App\Models\City;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Eloquent\TripEloquentRepository;
use App\Support\Cache\RepositoryCacheManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TripSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_search_filters_by_exact_date_only(): void
    {
        [$parisAddress, $lyonAddress, $driver] = $this->makeTripGraph();

        $match = Trip::query()->create([
            'departure_time' => '2026-03-26 10:00:00',
            'arrival_time' => '2026-03-26 12:00:00',
            'distance_km' => 100,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $parisAddress->id,
            'arrival_address_id' => $lyonAddress->id,
            'person_id' => $driver->id,
        ]);

        Trip::query()->create([
            'departure_time' => '2026-03-29 10:00:00',
            'arrival_time' => '2026-03-29 12:00:00',
            'distance_km' => 100,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $parisAddress->id,
            'arrival_address_id' => $lyonAddress->id,
            'person_id' => Person::query()->create([
                'first_name' => 'Other',
                'last_name' => 'Driver',
                'pseudo' => 'other_driver',
                'phone' => null,
                'car_id' => null,
            ])->id,
        ]);

        request()->merge(['page' => 1]);
        Cache::flush();

        $repo = new TripEloquentRepository(app(RepositoryCacheManager::class));
        $result = $repo->search('Paris', 'Lyon', '2026-03-26', null, 15);

        self::assertSame([$match->id], collect($result->items())->pluck('id')->all());
    }

    public function test_search_filters_by_date_and_minimum_time(): void
    {
        [$parisAddress, $lyonAddress, $driver] = $this->makeTripGraph();

        Trip::query()->create([
            'departure_time' => '2026-03-26 16:00:00',
            'arrival_time' => '2026-03-26 18:00:00',
            'distance_km' => 100,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $parisAddress->id,
            'arrival_address_id' => $lyonAddress->id,
            'person_id' => $driver->id,
        ]);

        $match = Trip::query()->create([
            'departure_time' => '2026-03-26 18:30:00',
            'arrival_time' => '2026-03-26 20:30:00',
            'distance_km' => 100,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $parisAddress->id,
            'arrival_address_id' => $lyonAddress->id,
            'person_id' => Person::query()->create([
                'first_name' => 'Late',
                'last_name' => 'Driver',
                'pseudo' => 'late_driver',
                'phone' => null,
                'car_id' => null,
            ])->id,
        ]);

        Trip::query()->create([
            'departure_time' => '2026-03-29 19:00:00',
            'arrival_time' => '2026-03-29 21:00:00',
            'distance_km' => 100,
            'available_seats' => 3,
            'smoking_allowed' => false,
            'departure_address_id' => $parisAddress->id,
            'arrival_address_id' => $lyonAddress->id,
            'person_id' => Person::query()->create([
                'first_name' => 'Future',
                'last_name' => 'Driver',
                'pseudo' => 'future_driver',
                'phone' => null,
                'car_id' => null,
            ])->id,
        ]);

        request()->merge(['page' => 1]);
        Cache::flush();

        $repo = new TripEloquentRepository(app(RepositoryCacheManager::class));
        $result = $repo->search('Paris', 'Lyon', '2026-03-26', '18:00', 15);

        self::assertSame([$match->id], collect($result->items())->pluck('id')->all());
    }

    /**
     * @return array{Address, Address, Person}
     */
    private function makeTripGraph(): array
    {
        $paris = City::query()->create(['name' => 'Paris', 'postal_code' => '75001']);
        $lyon = City::query()->create(['name' => 'Lyon', 'postal_code' => '69001']);

        $parisAddress = Address::query()->create([
            'street' => 'Rue de Paris',
            'street_number' => '1',
            'city_id' => $paris->id,
        ]);

        $lyonAddress = Address::query()->create([
            'street' => 'Rue de Lyon',
            'street_number' => '10',
            'city_id' => $lyon->id,
        ]);

        $driver = Person::query()->create([
            'first_name' => 'Main',
            'last_name' => 'Driver',
            'pseudo' => 'main_driver',
            'phone' => null,
            'car_id' => null,
        ]);

        return [$parisAddress, $lyonAddress, $driver];
    }
}

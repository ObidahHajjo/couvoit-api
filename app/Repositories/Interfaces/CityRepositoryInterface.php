<?php

namespace App\Repositories\Interfaces;

use App\Models\City;
use Illuminate\Support\Collection;

/**
 * Contract for city persistence operations.
 */
interface CityRepositoryInterface
{
    /**
     * Persist a new City and update caches.
     *
     * @param array<string,mixed> $data
     * @return City
     */
    public function create(array $data): City;

    /**
     * Delete a City and invalidate caches.
     *
     * @param City $city
     * @return void
     */
    public function delete(City $city): void;

    /**
     * Retrieve distinct postcodes.
     *
     * @return Collection<int,mixed>
     */
    public function getPostcodes(): Collection;

    /**
     * Find a city by (name, postal_code) or create it.
     *
     * @param string $cityName   City name as provided by the caller.
     * @param string $postalCode Postal code paired with the city name.
     *
     * @return City Matching or newly created city instance.
     */
    public function firstOrCreate(string $cityName, string $postalCode): City;

}

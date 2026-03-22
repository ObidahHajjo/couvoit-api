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
     * @param string $cityName
     * @param string $postalCode
     * @return City
     */
    public function firstOrCreate(string $cityName, string $postalCode);

}

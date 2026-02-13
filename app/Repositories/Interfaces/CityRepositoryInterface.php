<?php

namespace App\Repositories\Interfaces;

use App\Models\City;
use Illuminate\Support\Collection;

interface CityRepositoryInterface
{
    /**
     * Create a city.
     *
     * @param array $data
     * @return City
     */
    public function create(array $data): City;

    /**
     * Delete a city.
     *
     * City instance is usually provided by route model binding.
     *
     * @param City $city
     * @return void
     */
    public function delete(City $city): void;

    /**
     * Retrieve distinct postal codes.
     *
     * @return Collection<int,object> Each item contains {postal_code}.
     */
    public function getPostcodes(): Collection;

    public function firstOrCreate(string $cityName, string $postalCode);

}

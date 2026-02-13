<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use Illuminate\Support\Collection;

class CityEloquentRepository implements CityRepositoryInterface
{
    /** {@inheritDoc} */
    public function create(array $data): City
    {
        return City::query()->create($data);
    }

    /** {@inheritDoc} */
    public function delete(City $city): void
    {
        $city->delete();
    }

    /** {@inheritDoc} */
    public function getPostcodes(): Collection
    {
        return City::query()
            ->select('postal_code')
            ->distinct()
            ->orderBy('postal_code')
            ->get();
    }

    public function firstOrCreate(string $cityName, string $postalCode): City
    {
        return City::query()->firstOrCreate([
            'name'        => $cityName,
            'postal_code' => $postalCode,
        ]);
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of CityRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - City by (name, postal_code): cities:{normalizedName}:{postal_code}
 * - Distinct postcodes list:     cities:postcodes
 *
 * @author Covoiturage API
 *
 * @description Repository for managing City entities with caching support.
 */
readonly class CityEloquentRepository implements CityRepositoryInterface
{
    /**
     * Create a new city repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Normalize a city name for cache lookups.
     */
    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Create a new city.
     *
     * @param  array<string, mixed>  $data  City data containing name and postal_code
     * @return City The newly created City instance
     *
     * @throws QueryException When creation fails
     */
    public function create(array $data): City
    {
        $city = City::query()->create($data);

        $this->cache->putCity($city, (string) $city->name, (string) $city->postal_code);
        $this->cache->forgetCityPostcodes();

        return $city;
    }

    /**
     * Delete a city.
     *
     * @param  City  $city  The City instance to delete
     *
     * @throws \Exception When database deletion fails
     */
    public function delete(City $city): void
    {
        $name = $city->name;
        $postalCode = $city->postal_code;

        $city->delete();

        $this->cache->forgetCity($name, $postalCode);
        $this->cache->forgetCityPostcodes();
    }

    /**
     * Get all distinct postal codes ordered ascending.
     *
     * @return Collection<int, City> Collection of distinct postal codes
     */
    public function getPostcodes(): Collection
    {
        return $this->cache->rememberCityPostcodes(function () {
            return City::query()
                ->select('postal_code')
                ->distinct()
                ->orderBy('postal_code')
                ->get();
        });
    }

    /**
     * Find existing city or create a new one.
     *
     * @param  string  $cityName  The city name (will be normalized to lowercase)
     * @param  string  $postalCode  The postal code
     * @return City The existing or newly created City instance
     *
     * @throws QueryException When creation fails
     */
    public function firstOrCreate(string $cityName, string $postalCode): City
    {
        $cityName = $this->normalizeName($cityName);
        $postalCode = trim($postalCode);

        /** @var City|null $cached */
        $cached = $this->cache->rememberCity($cityName, $postalCode, function () use ($cityName, $postalCode) {
            return City::query()
                ->where('name', $cityName)
                ->where('postal_code', $postalCode)
                ->first();
        });

        if ($cached instanceof City && City::query()->whereKey($cached->id)->exists()) {
            return $cached;
        }

        if ($cached instanceof City) {
            $this->cache->forgetCity($cityName, $postalCode);
        }

        $existing = City::query()
            ->where('name', $cityName)
            ->where('postal_code', $postalCode)
            ->first();

        if ($existing) {
            $this->cache->putCity($existing, $cityName, $postalCode);

            return $existing;
        }

        $city = City::query()->create([
            'name' => $cityName,
            'postal_code' => $postalCode,
        ]);

        DB::afterCommit(function () use ($city, $cityName, $postalCode) {
            $this->cache->putCity($city, $cityName, $postalCode);
            $this->cache->forgetCityPostcodes();
        });

        return $city;
    }
}

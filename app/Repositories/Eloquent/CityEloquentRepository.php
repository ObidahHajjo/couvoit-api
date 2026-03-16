<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
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
 */
readonly class CityEloquentRepository implements CityRepositoryInterface
{
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** @inheritDoc */
    public function create(array $data): City
    {
        $city = City::query()->create($data);

        $this->cache->putCity($city, (string) $city->name, (string) $city->postal_code);
        $this->cache->forgetCityPostcodes();

        return $city;
    }

    /** @inheritDoc */
    public function delete(City $city): void
    {
        $name = $city->name;
        $postalCode = $city->postal_code;

        $city->delete();

        $this->cache->forgetCity($name, $postalCode);
        $this->cache->forgetCityPostcodes();
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
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

<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
class CityEloquentRepository implements CityRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    // ---------- Tags ----------

    /**
     * @return array<int,string>
     */
    private function tagCities(): array
    {
        return ['cities'];
    }

    /**
     * Tag scope for a specific (name, postal_code) pair.
     *
     * @param string $name
     * @param string $postal
     * @return array<int,string>
     */
    private function tagCity(string $name, string $postal): array
    {
        return array_merge(
            $this->tagCities(),
            ['city:' . $this->normalizeName($name) . ':' . trim($postal)]
        );
    }

    /**
     * @return array<int,string>
     */
    private function tagPostcodes(): array
    {
        return ['cities', 'cities:postcodes'];
    }

    // ---------- Keys ----------

    private function keyPostcodes(): string
    {
        return 'cities:postcodes';
    }

    private function keyCity(string $name, string $postal): string
    {
        return 'cities:' . $this->normalizeName($name) . ':' . trim($postal);
    }

    /**
     * Normalize city name for consistent caching and lookup.
     *
     * @param string $name
     * @return string
     */
    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** @inheritDoc */
    public function create(array $data): City
    {
        $city = City::query()->create($data);

        Cache::tags($this->tagCity((string) $city->name, (string) $city->postal_code))
            ->put(
                $this->keyCity((string) $city->name, (string) $city->postal_code),
                $city,
                self::TTL_SECONDS
            );

        // New city may introduce a new postal code
        Cache::tags($this->tagPostcodes())->forget($this->keyPostcodes());

        return $city;
    }

    /** @inheritDoc */
    public function delete(City $city): void
    {
        $city->delete();

        Cache::tags($this->tagCity($city->name, $city->postal_code))->flush();
        Cache::tags($this->tagPostcodes())->forget($this->keyPostcodes());
    }

    /** @inheritDoc */
    public function getPostcodes(): Collection
    {
        /** @var Collection<int,mixed> $postcodes */
        $postcodes = Cache::tags($this->tagPostcodes())
            ->remember($this->keyPostcodes(), self::TTL_SECONDS, function () {
                return City::query()
                    ->select('postal_code')
                    ->distinct()
                    ->orderBy('postal_code')
                    ->get();
            });

        return $postcodes;
    }

    /** @inheritDoc */
    public function firstOrCreate(string $cityName, string $postalCode): City
    {
        $cityName = $this->normalizeName($cityName);
        $postalCode = trim($postalCode);

        $key  = $this->keyCity($cityName, $postalCode);
        $tags = $this->tagCity($cityName, $postalCode);

        /** @var City|null $cached */
        $cached = Cache::tags($tags)->get($key);
        if ($cached instanceof City) {
            // Safety: if cache contains a rolled-back city, ignore it
            if (City::query()->whereKey($cached->id)->exists()) {
                return $cached;
            }
            Cache::tags($tags)->forget($key);
        }

        // DB read first (no creation side-effects)
        $existing = City::query()
            ->where('name', $cityName)
            ->where('postal_code', $postalCode)
            ->first();

        if ($existing) {
            Cache::tags($tags)->put($key, $existing, self::TTL_SECONDS);
            return $existing;
        }

        // Create (may be inside a transaction)
        $city = City::query()->create([
            'name' => $cityName,
            'postal_code' => $postalCode,
        ]);

        // Only cache after the transaction COMMITs
        DB::afterCommit(function () use ($tags, $key, $city) {
            Cache::tags($tags)->put($key, $city, self::TTL_SECONDS);
            Cache::tags($this->tagPostcodes())->forget($this->keyPostcodes());
        });

        return $city;
    }
}

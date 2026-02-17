<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CityEloquentRepository implements CityRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    // ---------- Tags ----------
    private function tagCities(): array { return ['cities']; }
    private function tagCity(string $name, string $postal): array
    {
        return array_merge(
            $this->tagCities(),
            ['city:' . $this->normalizeName($name) . ':' . trim($postal)]
        );
    }

    private function tagPostcodes(): array { return ['cities', 'cities:postcodes']; }

    // ---------- Keys ----------
    private function keyPostcodes(): string
    {
        return 'cities:postcodes';
    }

    private function keyCity(string $name, string $postal): string
    {
        return 'cities:' . $this->normalizeName($name) . ':' . trim($postal);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** @inheritDoc */
    public function create(array $data): City
    {
        $city = City::query()->create($data);

        Cache::tags($this->tagCity($city->name, $city->postal_code))
            ->put($this->keyCity($city->name, $city->postal_code), $city, self::TTL_SECONDS);

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
        /** @var Collection $postcodes */
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
        $cityName = trim($cityName);
        $postalCode = trim($postalCode);

        $key = $this->keyCity($cityName, $postalCode);

        /** @var City $city */
        $city = Cache::tags($this->tagCity($cityName, $postalCode))
            ->remember($key, self::TTL_SECONDS, function () use ($cityName, $postalCode) {
                return City::query()->firstOrCreate([
                    'name' => $cityName,
                    'postal_code' => $postalCode,
                ]);
            });

        // Postcodes list might change if a new city was created
        Cache::tags($this->tagPostcodes())->forget($this->keyPostcodes());

        return $city;
    }
}

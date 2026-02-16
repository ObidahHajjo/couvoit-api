<?php

namespace App\Repositories\Eloquent;

use App\Models\City;
use App\Repositories\Interfaces\CityRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CityEloquentRepository implements CityRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function keyPostcodes(): string
    {
        return 'cities:postcodes';
    }

    private function keyCity(string $name, string $postal): string
    {
        return 'cities:' . mb_strtolower(trim($name)) . ':' . trim($postal);
    }

    /** @inheritDoc */
    public function create(array $data): City
    {
        $city = City::query()->create($data);

        Cache::forget($this->keyPostcodes());
        Cache::forget($this->keyCity((string)$city->name, (string)$city->postal_code));

        return $city;
    }

    /** @inheritDoc */
    public function delete(City $city): void
    {
        $key = $this->keyCity($city->name, $city->postal_code);

        $city->delete();

        Cache::forget($this->keyPostcodes());
        Cache::forget($key);
    }

    public function getPostcodes(): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyPostcodes(), self::TTL_SECONDS, function () {
            return City::query()
                ->select('postal_code')
                ->distinct()
                ->orderBy('postal_code')
                ->get();
        });

        return $cached;
    }

    /**
     * @inheritDoc
     */
    public function firstOrCreate(string $cityName, string $postalCode): City
    {
        $cityName = trim($cityName);
        $postalCode = trim($postalCode);

        $key = $this->keyCity($cityName, $postalCode);

        /** @var City $city */
        $city = Cache::remember($key, self::TTL_SECONDS, function () use ($cityName, $postalCode) {
            return City::query()->firstOrCreate([
                'name' => $cityName,
                'postal_code' => $postalCode,
            ]);
        });

        // postcodes list might change if a new city was created
        Cache::forget($this->keyPostcodes());

        return $city;
    }
}

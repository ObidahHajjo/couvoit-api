<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CarRepositoryEloquent implements CarRepositoryInterface
{
    private const TTL_SECONDS = 600;

    private function keyAll(): string { return 'cars:all'; }
    private function keyById(int $id): string { return "cars:$id"; }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return Car::query()->with(['model.brand', 'model.type', 'color'])->get();
        });

        return $cached;
    }

    public function find(int $id): ?Car
    {
        return Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Car::query()->with(['model.brand', 'model.type', 'color'])->find($id);
        });
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(int $id): Car
    {
        /** @var Car $car */
        $car = Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Car::query()->with(['model.brand', 'model.type', 'color'])->findOrFail($id);
        });

        return $car;
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Car
    {
        $car = Car::query()->create($data)->loadMissing(['model.brand', 'model.type', 'color']);

        Cache::put($this->keyById((int) $car->id), $car, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $car;
    }

    /**
     * @inheritDoc
     */
    public function update(Car $car, array $data): bool
    {
        $ok = $car->update($data);
        $car->refresh()->loadMissing(['model.brand', 'model.type', 'color']);

        Cache::put($this->keyById($car->id), $car, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $ok;
    }

    /**
     * @inheritDoc
     */
    public function delete(Car $car): void
    {
        $id = $car->id;
        $car->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyAll());
    }
}

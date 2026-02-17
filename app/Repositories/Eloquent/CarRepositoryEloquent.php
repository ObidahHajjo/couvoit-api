<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CarRepositoryEloquent implements CarRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function tagCars(): array { return ['cars']; }
    private function tagCar(int $id): array { return ['cars', 'car:' . $id]; }

    private function keyAll(): string { return 'cars:all'; }
    private function keyById(int $id): string { return 'cars:' . $id; }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Car> $cars */
        $cars = Cache::tags($this->tagCars())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Car::query()->with(['model.brand', 'model.type', 'color'])->get();
            });

        // Optional: warm per-car caches so find/findOrFail can hit cache after all()
        foreach ($cars as $car) {
            Cache::tags($this->tagCar($car->id))
                ->put($this->keyById($car->id), $car, self::TTL_SECONDS);
        }

        return $cars;
    }

    /** @inheritDoc */
    public function find(int $id): ?Car
    {
        /** @var Car|null $car */
        $car = Cache::tags($this->tagCar($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Car::query()->with(['model.brand', 'model.type', 'color'])->find($id);
            });

        return $car;
    }

    /** @inheritDoc */
    public function findOrFail(int $id): Car
    {
        /** @var Car $car */
        $car = Cache::tags($this->tagCar($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Car::query()->with(['model.brand', 'model.type', 'color'])->findOrFail($id);
            });

        return $car;
    }

    /** @inheritDoc */
    public function create(array $data): Car
    {
        $car = Car::query()->create($data)->loadMissing(['model.brand', 'model.type', 'color']);

        Cache::tags($this->tagCar($car->id))
            ->put($this->keyById($car->id), $car, self::TTL_SECONDS);

        Cache::tags($this->tagCars())->forget($this->keyAll());

        return $car;
    }

    /** @inheritDoc */
    public function update(Car $car, array $data): bool
    {
        $ok = $car->update($data);
        $car->refresh()->loadMissing(['model.brand', 'model.type', 'color']);

        Cache::tags($this->tagCar($car->id))
            ->put($this->keyById($car->id), $car, self::TTL_SECONDS);

        Cache::tags($this->tagCars())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function delete(Car $car): void
    {
        $id = $car->id;

        $car->delete();

        Cache::tags($this->tagCar($id))->flush();
        Cache::tags($this->tagCars())->forget($this->keyAll());
    }
}

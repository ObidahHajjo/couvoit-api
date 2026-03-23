<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of CarRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Loaded relations:
 * - model.brand
 * - model.type
 * - color
 */
readonly class CarRepositoryEloquent implements CarRepositoryInterface
{
    /**
     * Create a new car repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Car> $cars */
        $cars = $this->cache->rememberCarsAll(function () {
            return Car::query()
                ->with(['model.brand', 'model.type', 'color'])
                ->get();
        });

        foreach ($cars as $car) {
            $this->cache->putCar($car);
        }

        return $cars;
    }

    /** @inheritDoc */
    public function find(int $id): ?Car
    {
        /** @var Car|null $car */
        $car = $this->cache->rememberCarById($id, function () use ($id) {
            return Car::query()
                ->with(['model.brand', 'model.type', 'color'])
                ->find($id);
        });

        return $car;
    }

    /** @inheritDoc */
    public function findOrFail(int $id): Car
    {
        /** @var Car $car */
        $car = $this->cache->rememberCarById($id, function () use ($id) {
            return Car::query()
                ->with(['model.brand', 'model.type', 'color'])
                ->findOrFail($id);
        });

        return $car;
    }

    /** @inheritDoc */
    public function create(array $data): Car
    {
        $car = Car::query()
            ->create($data)
            ->load(['model.brand', 'model.type', 'color']);

        $this->cache->putCar($car);
        $this->cache->forgetCarsAll();
        $this->cache->invalidatePersonsByCarId($car->id);

        return $car;
    }

    /** @inheritDoc */
    public function update(Car $car, array $data): bool
    {
        $ok = $car->update($data);
        $car->refresh()->load(['model.brand', 'model.type', 'color']);

        $this->cache->putCar($car);
        $this->cache->forgetCarsAll();
        $this->cache->invalidatePersonsByCarId($car->id);

        return $ok;
    }

    /** @inheritDoc */
    public function delete(Car $car): void
    {
        $id = $car->id;

        $car->delete();

        $this->cache->forgetCar($id);
        $this->cache->forgetCarsAll();
        $this->cache->invalidatePersonsByCarId($id);
    }
}

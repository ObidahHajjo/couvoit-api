<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
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
 *
 * @author Covoiturage API
 *
 * @description Repository for managing Car entities with caching support.
 */
readonly class CarRepositoryEloquent implements CarRepositoryInterface
{
    /**
     * Create a new car repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Get all cars with model, brand, type, and color relations.
     *
     * @return Collection<int, Car> Collection of all Car instances
     */
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

    /**
     * Find a car by its ID.
     *
     * @param  int  $id  The car ID to find
     * @return Car|null The Car instance with relations if found
     */
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

    /**
     * Find a car by ID or throw an exception.
     *
     * @param  int  $id  The car ID to find
     * @return Car The Car instance with relations
     *
     * @throws ModelNotFoundException When car not found
     */
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

    /**
     * Create a new car.
     *
     * @param  array<string, mixed>  $data  Car data to create
     * @return Car The newly created Car instance with relations
     *
     * @throws QueryException When creation fails
     */
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

    /**
     * Update a car with new data.
     *
     * @param  Car  $car  The Car instance to update
     * @param  array<string, mixed>  $data  New data to apply
     * @return bool True if update was successful
     */
    public function update(Car $car, array $data): bool
    {
        $ok = $car->update($data);
        $car->refresh()->load(['model.brand', 'model.type', 'color']);

        $this->cache->putCar($car);
        $this->cache->forgetCarsAll();
        $this->cache->invalidatePersonsByCarId($car->id);

        return $ok;
    }

    /**
     * Delete a car.
     *
     * @param  Car  $car  The Car instance to delete
     *
     * @throws \Exception When database deletion fails
     */
    public function delete(Car $car): void
    {
        $id = $car->id;

        $car->delete();

        $this->cache->forgetCar($id);
        $this->cache->forgetCarsAll();
        $this->cache->invalidatePersonsByCarId($id);
    }

    /**
     * Paginate all cars for admin panel.
     *
     * @param  int  $perPage  Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of Car instances
     */
    public function paginateForAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return Car::query()
            ->with(['model.brand', 'color'])
            ->paginate($perPage);
    }
}

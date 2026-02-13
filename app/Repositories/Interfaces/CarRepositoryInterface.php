<?php

namespace App\Repositories\Interfaces;

use App\Models\Car;
use Illuminate\Support\Collection;

interface CarRepositoryInterface
{
    /**
     * Retrieve all cars with required relations.
     *
     * @return Collection<int,Car>
     */
    public function all(): Collection;

    /**
     * Find a car by id (with relations).
     *
     * @param int $id
     * @return Car|null
     */
    public function find(int $id): ?Car;

    /**
     * Find a car by id or fail (with relations).
     *
     * @param int $id
     * @return Car
     */
    public function findOrFail(int $id): Car;

    /**
     * Create a car.
     *
     * @param array $data
     * @return Car
     */
    public function create(array $data): Car;

    /**
     * Update a given car.
     *
     * @param Car $car
     * @param array $data
     * @return true if updated, false otherwise
     */
    public function update(Car $car, array $data): bool;

    /**
     * Delete a given car.
     *
     * @param Car $car
     * @return void
     */
    public function delete(Car $car): void;
}

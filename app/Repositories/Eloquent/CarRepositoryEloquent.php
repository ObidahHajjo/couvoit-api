<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Interfaces\CarRepositoryInterface;
use Illuminate\Support\Collection;

class CarRepositoryEloquent implements CarRepositoryInterface
{
    /** {@inheritDoc} */
    public function all(): Collection
    {
        return Car::query()->with(['model.brand', 'model.type', 'color'])->get();
    }

    /** {@inheritDoc} */
    public function find(int $id): ?Car
    {
        return Car::query()->with(['model.brand', 'model.type', 'color'])->find($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Car
    {
        return Car::query()->with(['model.brand', 'model.type', 'color'])->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function create(array $data): Car
    {
        return Car::query()->create($data);
    }

    /** {@inheritDoc} */
    public function update(Car $car, array $data): bool
    {
        return $car->update($data);
    }

    /** {@inheritDoc} */
    public function delete(Car $car): void
    {
        $car->delete();
    }
}

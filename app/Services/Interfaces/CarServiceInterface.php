<?php

namespace App\Services\Interfaces;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Models\Car;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Throwable;

interface CarServiceInterface
{
    /**
     * Retrieve all cars.
     *
     * @return Collection<int, Car> Collection of cars.
     */
    public function getCars(): Collection;

    /**
     * Find a car.
     *
     * @param Car $car car Object.
     * @return Car The car if found
     * @throws ModelNotFoundException
     */
    public function findCar(Car $car): Car;

    /**
     * Create a new car and attach it to the given person.
     *
     * This method creates or fetches required reference entities:
     * - Brand
     * - Type
     * - Model
     * - Color
     *
     * Then creates the car and assigns it to the authenticated user by setting:
     * persons.car_id = cars.id
     *
     * All operations are wrapped inside a single database transaction.
     *
     * @param CarCreateData $dto Validated DTO car creation data.
     * @param Person $person Authenticated user (Person model).
     *
     * @return Car The created car.
     *
     * @throws Throwable If the transaction fails.
     */
    public function createCar(CarCreateData $dto, Person $person): Car;

    /**
     * Update an existing car.
     *
     * This method updates the car's references (model_id and/or color_id).
     * If model data is provided, it will create or fetch brand/type/model.
     * If color data is provided, it will create or fetch color.
     *
     * If no editable data is provided, returns false.
     *
     * @param Car $car Car Object.
     * @param CarUpdateData $dto Validated update payload.
     *
     * @return Car model instance
     *
     * @throws Throwable If the transaction fails.
     */
    public function updateCar(Car $car, CarUpdateData $dto): Car;

    /**
     * Delete a car by its ID.
     *
     * @param Car $car Car Object.
     *
     * @return void
     *
     * @throws ModelNotFoundException If deletion fails.
     */
    public function deleteCar(Car $car): void;
}

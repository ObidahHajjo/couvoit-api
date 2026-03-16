<?php

namespace App\Services\Interfaces;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Models\Car;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Contract for car-related application services.
 */
interface CarServiceInterface
{
    /**
     * Retrieve all cars.
     *
     * @return Collection<int, Car> Collection of cars.
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function getCars(): Collection;

    /**
     * Retrieve a specific car.
     *
     * @param Car $car Car model instance (resolved via route model binding).
     *
     * @return Car
     *
     * @throws ModelNotFoundException If the car does not exist in persistence.
     * @throws Throwable              Propagates any repository or infrastructure-level exception.
     */
    public function findCar(Car $car): Car;

    /**
     * Create a new car and attach it to the given person.
     *
     * This method:
     * - Creates or fetches Brand
     * - Creates or fetches Type
     * - Creates or fetches Model
     * - Creates or fetches Color
     * - Creates the Car
     * - Assigns persons.car_id = cars.id
     *
     * All operations must be wrapped in a single database transaction.
     *
     * @param CarCreateData $dto    Validated DTO containing car creation data.
     * @param Person        $person Authenticated Person aggregate.
     *
     * @return Car The created Car entity.
     *
     * @throws Throwable If the transaction fails or any sub-operation throws.
     */
    public function createCar(CarCreateData $dto, Person $person): Car;

    /**
     * Update an existing car.
     *
     * This method updates:
     * - model_id (creating/fetching Brand/Type/Model if necessary)
     * - color_id (creating/fetching Color if necessary)
     *
     * @param Car            $car Car model instance.
     * @param CarUpdateData  $dto Validated update payload.
     *
     * @return Car Updated Car entity.
     *
     * @throws Throwable If the transaction fails or any sub-operation throws.
     */
    public function updateCar(Car $car, CarUpdateData $dto): Car;

    /**
     * Delete a car.
     *
     * @param Car $car Car model instance.
     *
     * @return void
     *
     * @throws ModelNotFoundException If the car cannot be found.
     * @throws Throwable              Propagates any repository or infrastructure-level exception.
     */
    public function deleteCar(Car $car): void;


    /**
     * Local DB first, external API second.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $q, string $brand): array;
}

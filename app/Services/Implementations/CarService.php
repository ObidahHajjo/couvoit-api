<?php

namespace App\Services\Implementations;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Exceptions\ConflictException;
use App\Exceptions\InactiveUserException;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;
use App\Models\Person;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Interfaces\CarServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class CarService implements CarServiceInterface
{
    public function __construct(
        private CarRepositoryInterface        $carRepository,
        private CarReferenceResolverInterface $carReferenceResolver,
        private PersonRepositoryInterface     $personRepository
    ) {}

    public function getCars(): Collection
    {
        return $this->carRepository->all();
    }

    public function findCar(Car $car): Car
    {
        return $car;
    }

    public function createCar(CarCreateData $dto, Person $person): Car
    {
        if (!$person->is_active) {
            throw new InactiveUserException();
        }

        if ($person->car_id !== null) {
            throw new ConflictException('User already has a car.');
        }

        return DB::transaction(function () use ($dto, $person) {

            // Resolver receives normalized structure (NOT request keys)
            $refs = $this->carReferenceResolver->resolveForCreate([
                'brand' => ['name' => $dto->brandName],
                'type'  => ['name' => $dto->typeName],
                'model' => ['name' => $dto->modelName, 'seats' => $dto->seats],
                'color' => ['hex_code' => $dto->colorHex],
            ]);

            $car = $this->carRepository->create([
                'color_id'      => $refs->colorId,
                'model_id'      => $refs->modelId,
                'license_plate' => $dto->licensePlate,
            ]);

            $this->personRepository->attachCar($person, $car->id);

            // return loaded (repo should eager load relationships)
            return $this->carRepository->findOrFail($car->id);
        });
    }

    public function updateCar(Car $car, CarUpdateData $dto): Car
    {
        if ($dto->isEmpty()) {
            throw new ValidationLogicException('Nothing to update.');
        }

        return DB::transaction(function () use ($car, $dto) {

            $editable = [];

            // model update: only if modelName provided (and optionally brand/type/seats)
            if ($dto->modelName !== null) {
                $modelId = $this->carReferenceResolver->resolveModelForUpdate($car, [
                    'brand' => ['name' => $dto->brandName],
                    'type'  => ['name' => $dto->typeName],
                    'model' => ['name' => $dto->modelName, 'seats' => $dto->seats],
                ]);

                $editable['model_id'] = $modelId;
            }

            // color update
            if ($dto->colorHex !== null) {
                $colorId = $this->carReferenceResolver->resolveColorForUpdate([
                    'color' => ['hex_code' => $dto->colorHex],
                ]);

                $editable['color_id'] = $colorId;
            }

            // plate update
            if ($dto->licensePlate !== null) {
                $editable['license_plate'] = $dto->licensePlate;
            }

            if (empty($editable)) {
                throw new ValidationLogicException('Nothing to update.');
            }

            $this->carRepository->update($car, $editable);

            return $this->carRepository->findOrFail($car->id);
        });
    }

    public function deleteCar(Car $car): void
    {
        $this->carRepository->delete($car);
    }
}

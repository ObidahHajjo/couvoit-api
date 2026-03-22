<?php

namespace App\Services\Implementations;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Person;
use App\Models\Trip;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Repositories\Interfaces\CarRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Interfaces\CarServiceInterface;
use App\Support\Cache\RepositoryCacheManager;
use App\Support\Car\CarCatalogNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

readonly class CarService implements CarServiceInterface
{
    public function __construct(
        private CarRepositoryInterface $carRepository,
        private CarReferenceResolverInterface $carReferenceResolver,
        private PersonRepositoryInterface $personRepository,
        private CarModelRepositoryInterface $modelRepository,
        private CarCatalogNormalizer $normalizer,
        private RepositoryCacheManager $cache,
    ) {}

    /** {@inheritDoc} */
    public function getCars(): Collection
    {
        return $this->carRepository->all();
    }

    /** {@inheritDoc} */
    public function findCar(Car $car): Car
    {
        return $car;
    }

    /** {@inheritDoc} */
    public function createCar(CarCreateData $dto, Person $person): Car
    {
        if ($person->car_id !== null) {
            throw new ConflictException('User already has a car.');
        }

        $modelSearchKey = $this->normalizer->normalizeSearchKey($dto->modelName);

        return DB::transaction(function () use ($dto, $person, $modelSearchKey) {
            $refs = $this->carReferenceResolver->resolveForCreate([
                'brand' => ['name' => $dto->brandName],
                'type' => ['name' => $dto->typeName],
                'model' => ['name' => $dto->modelName, 'search_key' => $modelSearchKey],
                'color' => ['hex_code' => $dto->colorHex, 'name' => $dto->colorName],
            ]);

            $car = $this->carRepository->create([
                'color_id' => $refs->colorId,
                'model_id' => $refs->modelId,
                'license_plate' => $dto->licensePlate,
                'seats' => $dto->seats,
            ]);

            $this->personRepository->attachCar($person, $car->id);

            return $this->carRepository->findOrFail($car->id);
        });
    }

    /** {@inheritDoc} */
    public function updateCar(Car $car, CarUpdateData $dto): Car
    {
        if ($dto->isEmpty()) {
            throw new ValidationLogicException('Nothing to update.');
        }

        return DB::transaction(function () use ($car, $dto) {
            $editable = [];

            if ($dto->modelName !== null) {
                $modelSearchKey = $this->normalizer->normalizeSearchKey($dto->modelName);
                $modelId = $this->carReferenceResolver->resolveModelForUpdate($car, [
                    'brand' => ['name' => $dto->brandName],
                    'type' => ['name' => $dto->typeName],
                    'model' => ['name' => $dto->modelName, 'search_key' => $modelSearchKey],
                ]);

                $editable['model_id'] = $modelId;
            }

            if ($dto->colorHex !== null || $dto->colorName !== null) {
                $colorId = $this->carReferenceResolver->resolveColorForUpdate([
                    'color' => [
                        'name' => $dto->colorName,
                        'hex_code' => $dto->colorHex,
                    ],
                ]);

                if ($colorId === null) {
                    throw new ValidationLogicException(
                        'color.name and color.hex_code are required when updating color.'
                    );
                }

                $editable['color_id'] = $colorId;
            }

            if ($dto->licensePlate !== null) {
                $editable['license_plate'] = $dto->licensePlate;
            }

            if ($dto->seats !== null) {
                $editable['seats'] = $dto->seats;
            }

            if ($editable === []) {
                throw new ValidationLogicException('Nothing to update.');
            }

            $this->carRepository->update($car, $editable);

            return $this->carRepository->findOrFail($car->id);
        });
    }

    /** {@inheritDoc} */
    public function deleteCar(Car $car): void
    {
        $person = auth()->user()->person;
        $trips = $person->trips;
        if (! $trips->isEmpty()) {
            if ($trips->contains(fn (Trip $trip) => Carbon::parse($trip->departure_time)->isAfter(Carbon::now()))) {
                throw new ConflictException('You cannot delete a car that is in use.');
            }
        }
        $this->carRepository->delete($car);

        $this->cache->invalidatePersonsByCarId($car->id);
        $this->cache->invalidatePersonListAndItem($person->id);
    }

    /** {@inheritDoc} */
    public function search(string $q, string $brand): array
    {
        $q = trim($q);
        $brand = trim($brand);

        if ($q === '' || $brand === '') {
            return [];
        }

        $local = $this->searchLocalModels($q, $brand);

        if ($local !== []) {
            return $local;
        }

        return $this->searchExternalModels($q, $brand);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchLocalModels(string $q, string $brand): array
    {
        $brandKey = $this->normalizer->normalizeSearchKey($brand);
        $queryKey = $this->normalizer->normalizeSearchKey($q);

        if ($brandKey === '' || $queryKey === '') {
            return [];
        }

        /** @var EloquentCollection<int, CarModel> $models */
        $models = $this->modelRepository->findBySearchKey($brandKey, $queryKey);

        return $models
            ->map(function (CarModel $model): array {
                return [
                    'id' => $model->id,
                    'model' => [
                        'id' => $model->id,
                        'name' => $model->name,
                        'brand' => [
                            'id' => $model->brand?->id ?? 0,
                            'name' => $model->brand?->name ?? '',
                        ],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchExternalModels(string $q, string $brand): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->get(
                'https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMake/'.rawurlencode($brand),
                ['format' => 'json']
            );

        $response->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        /** @var array<int, array<string, mixed>> $results */
        $results = is_array($json['Results'] ?? null) ? $json['Results'] : [];

        $seen = [];
        $output = [];
        $id = 1;

        foreach ($results as $item) {
            $modelName = trim((string) ($item['Model_Name'] ?? ''));
            $makeName = trim((string) ($item['Make_Name'] ?? $brand));

            if ($modelName === '') {
                continue;
            }

            if (! $this->normalizer->containsNormalized($q, $modelName)) {
                continue;
            }

            $dedupeKey = $this->normalizer->normalizeSearchKey($makeName)
                .'|'
                .$this->normalizer->normalizeSearchKey($modelName);

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $output[] = [
                'id' => $id++,
                'model' => [
                    'id' => 0,
                    'name' => $modelName,
                    'brand' => [
                        'id' => 0,
                        'name' => $makeName,
                    ],
                ],
            ];
        }

        return $output;
    }
}

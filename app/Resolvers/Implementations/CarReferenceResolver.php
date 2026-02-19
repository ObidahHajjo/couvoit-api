<?php

namespace App\Resolvers\Implementations;

use App\DTOS\Car\ResolvedCarRefs;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use App\Repositories\Interfaces\CarModelRepositoryInterface;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;

/**
 * Resolves and/or creates reference entities needed to create or update a Car.
 *
 * Responsibilities:
 * - Create or reuse Brand, Type, CarModel, Color based on incoming payload data
 * - Return resolved foreign keys to be used when persisting a Car
 */
final readonly class CarReferenceResolver implements CarReferenceResolverInterface
{
    /**
     * @param BrandRepositoryInterface    $brands
     * @param TypeRepositoryInterface     $types
     * @param CarModelRepositoryInterface $models
     * @param ColorRepositoryInterface    $colors
     */
    public function __construct(
        private BrandRepositoryInterface $brands,
        private TypeRepositoryInterface $types,
        private CarModelRepositoryInterface $models,
        private ColorRepositoryInterface $colors,
    ) {
    }

    /** @inheritDoc */
    public function resolveForCreate(array $data): ResolvedCarRefs
    {
        $brandName = data_get($data, 'brand.name');
        $typeName  = data_get($data, 'type.name');
        $modelName = data_get($data, 'model.name');
        $seats     = data_get($data, 'model.seats');
        $colorName = data_get($data, 'color.name');
        $hexCode   = data_get($data, 'color.hex_code');

        if (!is_string($brandName) || trim($brandName) === '') {
            throw new ValidationLogicException('brand.name is required.');
        }
        if (!is_string($typeName) || trim($typeName) === '') {
            throw new ValidationLogicException('type.name is required.');
        }
        if (!is_string($modelName) || trim($modelName) === '') {
            throw new ValidationLogicException('model.name is required.');
        }
        if ($seats === null) {
            throw new ValidationLogicException('model.seats is required.');
        }
        if (!is_string($colorName) || trim($colorName) === '') {
            throw new ValidationLogicException('color.name is required.');
        }
        if (!is_string($hexCode) || trim($hexCode) === '') {
            throw new ValidationLogicException('color.hex_code is required.');
        }

        $brand = $this->brands->createOrFirst(strtolower(trim($brandName)));
        $type  = $this->types->createOrFirst(strtolower(trim($typeName)));

        $model = $this->models->createOrFirst([
            'name'     => strtolower(trim($modelName)),
            'seats'    => (int) $seats,
            'brand_id' => $brand->id,
            'type_id'  => $type->id,
        ]);

        $color = $this->colors->createOrFirst([
            'name'     => strtolower(trim($colorName)),
            'hex_code' => strtolower(trim($hexCode)),
        ]);

        return new ResolvedCarRefs(
            brandId: $brand->id,
            typeId: $type->id,
            modelId: $model->id,
            colorId: $color->id,
        );
    }

    /** @inheritDoc */
    public function resolveModelForUpdate(Car $car, array $data): ?int
    {
        $modelName = data_get($data, 'model.name');

        if ($modelName === null) {
            return null;
        }

        $car->loadMissing('model');

        $currentBrandId = $car->model->brand_id;
        $currentTypeId  = $car->model->type_id;
        $currentSeats   = $car->model->seats;

        $brandName = data_get($data, 'brand.name');
        $typeName  = data_get($data, 'type.name');
        $seats     = data_get($data, 'model.seats');

        $brandId = $currentBrandId;
        if ($brandName !== null && trim((string) $brandName) !== '') {
            $brandId = $this->brands->createOrFirst(strtolower(trim((string) $brandName)))->id;
        }

        $typeId = $currentTypeId;
        if ($typeName !== null && trim((string) $typeName) !== '') {
            $typeId = $this->types->createOrFirst(strtolower(trim((string) $typeName)))->id;
        }

        $finalSeats = $currentSeats;
        if ($seats !== null) {
            $finalSeats = (int) $seats;
        }

        $model = $this->models->createOrFirst([
            'name'     => strtolower(trim((string) $modelName)),
            'brand_id' => $brandId,
            'type_id'  => $typeId,
            'seats'    => $finalSeats,
        ]);

        return $model->id;
    }

    /** @inheritDoc */
    public function resolveColorForUpdate(array $data): ?int
    {
        $colorName = data_get($data, 'color.name');

        if ($colorName === null) {
            return null;
        }

        $hexCode = data_get($data, 'color.hex_code');

        if ($hexCode === null || trim((string) $hexCode) === '') {
            throw new ValidationLogicException('color.hex_code is required when updating color.');
        }

        $color = $this->colors->createOrFirst([
            'name'     => strtolower(trim((string) $colorName)),
            'hex_code' => strtolower(trim((string) $hexCode)),
        ]);

        return $color->id;
    }
}

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
    public function __construct(
        private BrandRepositoryInterface $brands,
        private TypeRepositoryInterface $types,
        private CarModelRepositoryInterface $models,
        private ColorRepositoryInterface $colors,
    ) {}

    /** {@inheritDoc} */
    public function resolveForCreate(array $data): ResolvedCarRefs
    {
        $brandName = data_get($data, 'brand.name');
        $modelSearchKey = data_get($data, 'model.search_key');
        $typeName = data_get($data, 'type.name');
        $modelName = data_get($data, 'model.name');
        $colorName = data_get($data, 'color.name');
        $hexCode = data_get($data, 'color.hex_code');

        if (! is_string($brandName) || trim($brandName) === '') {
            throw new ValidationLogicException('brand.name is required.');
        }
        if (! is_string($modelSearchKey) || trim($modelSearchKey) === '') {
            throw new ValidationLogicException('model.search_key is required.');
        }
        if (! is_string($typeName) || trim($typeName) === '') {
            throw new ValidationLogicException('type.name is required.');
        }
        if (! is_string($modelName) || trim($modelName) === '') {
            throw new ValidationLogicException('model.name is required.');
        }
        if (! is_string($colorName) || trim($colorName) === '') {
            throw new ValidationLogicException('color.name is required.');
        }
        if (! is_string($hexCode) || trim($hexCode) === '') {
            throw new ValidationLogicException('color.hex_code is required.');
        }

        $brand = $this->brands->createOrFirst(strtolower(trim($brandName)));
        $type = $this->types->createOrFirst(strtolower(trim($typeName)));

        $model = $this->models->createOrFirst([
            'name' => strtolower(trim($modelName)),
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'search_key' => $modelSearchKey,
        ]);

        $color = $this->colors->createOrFirst([
            'name' => strtolower(trim($colorName)),
            'hex_code' => strtolower(trim($hexCode)),
        ]);

        return new ResolvedCarRefs(
            brandId: $brand->id,
            typeId: $type->id,
            modelId: $model->id,
            colorId: $color->id,
        );
    }

    /** {@inheritDoc} */
    public function resolveModelForUpdate(Car $car, array $data): ?int
    {
        $modelName = data_get($data, 'model.name');
        $modelSearchKey = data_get($data, 'model.search_key');

        if ($modelName === null || $modelSearchKey === null) {
            return null;
        }

        $car->loadMissing('model');

        $currentBrandId = $car->model->brand_id;
        $currentTypeId = $car->model->type_id;

        $brandName = data_get($data, 'brand.name');
        $typeName = data_get($data, 'type.name');

        $brandId = $currentBrandId;
        if ($brandName !== null && trim((string) $brandName) !== '') {
            $brandId = $this->brands->createOrFirst(strtolower(trim((string) $brandName)))->id;
        }

        $typeId = $currentTypeId;
        if ($typeName !== null && trim((string) $typeName) !== '') {
            $typeId = $this->types->createOrFirst(strtolower(trim((string) $typeName)))->id;
        }

        $model = $this->models->createOrFirst([
            'name' => strtolower(trim((string) $modelName)),
            'brand_id' => $brandId,
            'type_id' => $typeId,
            'search_key' => $modelSearchKey,
        ]);

        return $model->id;
    }

    /** {@inheritDoc} */
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
            'name' => strtolower(trim((string) $colorName)),
            'hex_code' => strtolower(trim((string) $hexCode)),
        ]);

        return $color->id;
    }
}

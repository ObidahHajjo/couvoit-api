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

final readonly class CarReferenceResolver implements CarReferenceResolverInterface
{
    public function __construct(
        private BrandRepositoryInterface    $brands,
        private TypeRepositoryInterface     $types,
        private CarModelRepositoryInterface $models,
        private ColorRepositoryInterface    $colors,
    )
    {
    }

    public function resolveForCreate(array $data): ResolvedCarRefs
    {
        $brand = $this->brands->createOrFirst(strtolower($data["brand"]["name"]));
        $type = $this->types->createOrFirst(strtolower($data["type"]["name"]));

        $model = $this->models->createOrFirst([
            "name" => strtolower($data["model"]["name"]),
            "seats" => (int)$data["model"]["seats"],
            "brand_id" => $brand->id,
            "type_id" => $type->id,
        ]);

        $color = $this->colors->createOrFirst([
            "name" => strtolower($data["color"]["name"]),
            "hex_code" => $data["color"]["hex_code"],
        ]);

        return new ResolvedCarRefs(
            brandId: $brand->id,
            typeId: $type->id,
            modelId: $model->id,
            colorId: $color->id,
        );
    }

    public function resolveModelForUpdate(Car $car, array $data): ?int
    {
        $modelName = data_get($data, 'model.name');

        if ($modelName === null) {
            return null;
        }

        // Ensure current model is loaded (if not eager loaded)
        $car->loadMissing('model');

        // default values from current car model
        $currentBrandId = $car->model->brand_id;
        $currentTypeId  = $car->model->type_id;
        $currentSeats   = $car->model->seats;

        $brandName = data_get($data, 'brand.name');
        $typeName  = data_get($data, 'type.name');
        $seats     = data_get($data, 'model.seats');

        // Resolve brand (if provided, else reuse existing brand)
        $brandId = $currentBrandId;
        if ($brandName !== null && trim((string)$brandName) !== '') {
            $brand = $this->brands->createOrFirst(strtolower(trim((string)$brandName)));
            $brandId = $brand->id;
        }

        // Resolve type (if provided, else reuse existing type)
        $typeId = $currentTypeId;
        if ($typeName !== null && trim((string)$typeName) !== '') {
            $type = $this->types->createOrFirst(strtolower(trim((string)$typeName)));
            $typeId = $type->id;
        }

        // Seats (if provided, else reuse current)
        $finalSeats = $currentSeats;
        if ($seats !== null) {
            $finalSeats = (int) $seats;
        }

        // Resolve model (create or reuse)
        $model = $this->models->createOrFirst([
            'name'     => strtolower(trim((string)$modelName)),
            'brand_id' => $brandId,
            'type_id'  => $typeId,
            'seats'    => $finalSeats,
        ]);

        return $model->id;
    }

    public function resolveColorForUpdate(array $data): ?int
    {
        $colorName = data_get($data, 'color.name');

        if ($colorName === null) return null;

        $hexCode = data_get($data, 'color.hex_code');

        if ($hexCode === null || trim((string)$hexCode) === '') {
            throw new ValidationLogicException("color.hex_code is required when updating color.");
        }

        $color = $this->colors->createOrFirst([
            'name'     => strtolower(trim((string)$colorName)),
            'hex_code' => strtolower(trim((string)$hexCode)),
        ]);

        return $color->id;
    }

}

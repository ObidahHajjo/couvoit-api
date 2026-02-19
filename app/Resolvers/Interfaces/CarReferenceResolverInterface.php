<?php

namespace App\Resolvers\Interfaces;

use App\DTOS\Car\ResolvedCarRefs;
use App\Exceptions\ValidationLogicException;
use App\Models\Car;

interface CarReferenceResolverInterface
{
    /**
     * Resolve references required for creating a car.
     *
     * Expected payload structure:
     * - brand.name
     * - type.name
     * - model.name
     * - model.seats
     * - color.name
     * - color.hex_code
     *
     * @param array<string,mixed> $data
     * @return ResolvedCarRefs
     *
     * @throws ValidationLogicException If the payload is missing required keys/values.
     */
    public function resolveForCreate(array $data): ResolvedCarRefs;

    /**
     * Resolve a CarModel id for updating a car based on the update payload.
     *
     * If "model.name" is not provided, returns null (no model change).
     * If provided, this will:
     * - Load current car model as defaults
     * - Resolve brand/type if provided, otherwise reuse current model's brand/type
     * - Resolve seats if provided, otherwise reuse current model's seats
     * - Create or reuse the matching CarModel and return its id
     *
     * @param Car                $car
     * @param array<string,mixed> $data
     * @return int|null
     */
    public function resolveModelForUpdate(Car $car, array $data): ?int;

    /**
     * Resolve a Color id for updating a car based on the update payload.
     *
     * If "color.name" is not provided, returns null (no color change).
     * If "color.name" is provided, "color.hex_code" is required.
     *
     * @param array<string,mixed> $data
     * @return int|null
     *
     * @throws ValidationLogicException When color.name is provided but color.hex_code is missing/blank.
     */
    public function resolveColorForUpdate(array $data): ?int;
}


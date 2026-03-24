<?php

namespace App\DTOS\Car;

use InvalidArgumentException;

/**
 * Immutable data transfer object for car creation input.
 *
 * @author Covoiturage API Team
 *
 * @description Represents validated car data required to create a new car entity.
 */
final readonly class CarCreateData
{
    /**
     * Create a new car creation data object.
     */
    public function __construct(
        public string $licensePlate,
        public string $modelName,
        public int $seats,
        public string $brandName,
        public string $typeName,
        public string $colorHex,
        public string $colorName,
    ) {}

    /**
     * Build DTO from raw request payload.
     *
     * Supports both normalized keys and legacy payload structure.
     *
     * @param  array<string, mixed>  $data  Raw request data
     * @return static New CarCreateData instance
     *
     * @throws InvalidArgumentException When required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        // normalize + support your current payload keys
        $license = $data['license_plate']
            ?? $data['carregistration']
            ?? null;

        $modelName = data_get($data, 'model.name');
        $seats = $data['seats'] ?? data_get($data, 'model.seats');
        $brandName = data_get($data, 'brand.name');
        $typeName = data_get($data, 'type.name');

        // align with schema: colors.hex_code
        $colorHex = data_get($data, 'color.hex_code')
            ?? data_get($data, 'color.hex')
            ?? data_get($data, 'color.code');

        $colorName = data_get($data, 'color.name');

        if (! is_string($license) || trim($license) === '') {
            throw new InvalidArgumentException('license_plate is required.');
        }

        if (! is_string($modelName) || trim($modelName) === '') {
            throw new InvalidArgumentException('model.name is required.');
        }

        if (! is_numeric($seats) || (int) $seats <= 0) {
            throw new InvalidArgumentException('seats must be a positive integer.');
        }

        if (! is_string($brandName) || trim($brandName) === '') {
            throw new InvalidArgumentException('brand.name is required.');
        }

        if (! is_string($typeName) || trim($typeName) === '') {
            throw new InvalidArgumentException('type.name is required.');
        }

        if (! is_string($colorHex) || trim($colorHex) === '') {
            throw new InvalidArgumentException('color.hex_code is required.');
        }

        if (! is_string($colorName) || trim($colorName) === '') {
            throw new InvalidArgumentException('color.name is required.');
        }

        return new self(
            licensePlate: strtoupper(trim($license)),
            modelName: strtolower(trim($modelName)),
            seats: (int) $seats,
            brandName: strtolower(trim($brandName)),
            typeName: strtolower(trim($typeName)),
            colorHex: strtolower(trim($colorHex)),
            colorName: strtolower(trim($colorName)),
        );
    }
}

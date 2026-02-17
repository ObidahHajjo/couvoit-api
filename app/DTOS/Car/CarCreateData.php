<?php

namespace App\DTOS\Car;

use InvalidArgumentException;

final readonly class CarCreateData
{
    public function __construct(
        public string $licensePlate,
        public string $modelName,
        public int $seats,
        public string $brandName,
        public string $typeName,
        public string $colorHex,
        public string $colorName,
    ) {}

    public static function fromArray(array $data): self
    {
        // normalize + support your current payload keys
        $license = $data['license_plate']
            ?? $data['carregistration']
            ?? null;

        $modelName = data_get($data, 'model.name');
        $seats     = data_get($data, 'model.seats');
        $brandName = data_get($data, 'brand.name');
        $typeName  = data_get($data, 'type.name');

        // align with your schema: colors.hex_code
        $colorHex  = data_get($data, 'color.hex_code')
            ?? data_get($data, 'color.hex')
            ?? data_get($data, 'color.code');

        $colorName = data_get($data, 'color.name');
        if (!is_string($license) || trim($license) === '') {
            throw new InvalidArgumentException('license_plate is required.');
        }

        return new self(
            licensePlate: strtoupper(trim($license)),
            modelName: strtolower(trim((string) $modelName)),
            seats: (int) $seats,
            brandName: strtolower(trim((string) $brandName)),
            typeName: strtolower(trim((string) $typeName)),
            colorHex: strtolower(trim((string) $colorHex)),
            colorName: strtolower(trim((string) $colorName))
        );
    }
}

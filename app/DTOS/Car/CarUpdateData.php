<?php

namespace App\DTOS\Car;

use InvalidArgumentException;

final readonly class CarUpdateData
{
    /**
     * @param string|null $licensePlate
     * @param string|null $modelName
     * @param int|null    $seats
     * @param string|null $brandName
     * @param string|null $typeName
     * @param string|null $colorHex
     * @param string|null $colorName
     */
    public function __construct(
        public ?string $licensePlate = null,
        public ?string $modelName = null,
        public ?int $seats = null,
        public ?string $brandName = null,
        public ?string $typeName = null,
        public ?string $colorHex = null,
        public ?string $colorName = null,
    )
    {
    }

    /**
     * Build DTO from raw request payload.
     *
     * Only provided fields will be mapped.
     *
     * @param array<string, mixed> $data
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $license = $data['license_plate']
            ?? $data['carregistration']
            ?? null;

        $colorHex = data_get($data, 'color.hex_code')
            ?? data_get($data, 'color.hex')
            ?? data_get($data, 'color.code');

        $colorName = data_get($data, 'color.name');

        $modelName = data_get($data, 'model.name');
        $seats = data_get($data, 'model.seats');
        $brandName = data_get($data, 'brand.name');
        $typeName = data_get($data, 'type.name');

        $normalizedSeats = null;
        if ($seats !== null) {
            if (!is_numeric($seats) || (int)$seats <= 0) {
                throw new InvalidArgumentException('model.seats must be a positive integer.');
            }
            $normalizedSeats = (int)$seats;
        }

        return new self(
            licensePlate: is_string($license) && trim($license) !== ''
                ? strtoupper(trim($license))
                : null,

            modelName: $modelName !== null
                ? strtolower(trim((string)$modelName))
                : null,

            seats: $normalizedSeats,

            brandName: $brandName !== null
                ? strtolower(trim((string)$brandName))
                : null,

            typeName: $typeName !== null
                ? strtolower(trim((string)$typeName))
                : null,

            colorHex: $colorHex !== null && trim((string)$colorHex) !== ''
                ? strtolower(trim((string)$colorHex))
                : null,

            colorName: $colorName !== null && trim((string)$colorName) !== ''
                ? strtolower(trim((string)$colorName))
                : null,
        );
    }

    /**
     * Determine whether no updatable field was provided.
     */
    public function isEmpty(): bool
    {
        return $this->licensePlate === null
            && $this->modelName === null
            && $this->seats === null
            && $this->brandName === null
            && $this->typeName === null
            && $this->colorHex === null
            && $this->colorName === null;
    }
}

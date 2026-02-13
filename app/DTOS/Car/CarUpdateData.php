<?php

namespace App\DTOS\Car;

final readonly class CarUpdateData
{
    public function __construct(
        public ?string $licensePlate = null,
        public ?string $modelName = null,
        public ?int $seats = null,
        public ?string $brandName = null,
        public ?string $typeName = null,
        public ?string $colorHex = null
    ) {}

    public static function fromArray(array $data): self
    {
        $license = $data['license_plate']
            ?? $data['carregistration']
            ?? null;

        $colorHex = data_get($data, 'color.hex_code')
            ?? data_get($data, 'color.hex')
            ?? data_get($data, 'color.code');

        return new self(
            licensePlate: is_string($license) && trim($license) !== '' ? strtoupper(trim($license)) : null,
            modelName: data_get($data, 'model.name') !== null ? strtolower(trim((string) data_get($data, 'model.name'))) : null,
            seats: data_get($data, 'model.seats') !== null ? (int) data_get($data, 'model.seats') : null,
            brandName: data_get($data, 'brand.name') !== null ? strtolower(trim((string) data_get($data, 'brand.name'))) : null,
            typeName: data_get($data, 'type.name') !== null ? strtolower(trim((string) data_get($data, 'type.name'))) : null,
            colorHex: $colorHex !== null ? strtolower(trim((string) $colorHex)) : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->licensePlate === null
            && $this->modelName === null
            && $this->seats === null
            && $this->brandName === null
            && $this->typeName === null
            && $this->colorHex === null;
    }
}

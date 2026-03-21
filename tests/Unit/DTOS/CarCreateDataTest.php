<?php

namespace Tests\Unit\DTOS;

use App\DTOS\Car\CarCreateData;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

/**
 * Class CarCreateDataTest
 *
 * Unit tests for CarCreateData::fromArray normalization and validation.
 */
class CarCreateDataTest extends TestCase
{
    /**
     * fromArray() should normalize and map supported keys.
     *
     *
     * @throws Throwable
     */
    public function test_from_array_normalizes_and_maps_keys(): void
    {
        $dto = CarCreateData::fromArray([
            'license_plate' => ' ab-123-cd ',
            'model' => ['name' => '  Golf  '],
            'seats' => 5,
            'brand' => ['name' => '  VW '],
            'type' => ['name' => '  Hatch '],
            'color' => ['hex_code' => ' #00AAFF ', 'name' => ' Sky '],
        ]);

        $this->assertSame('AB-123-CD', $dto->licensePlate);
        $this->assertSame('golf', $dto->modelName);
        $this->assertSame(5, $dto->seats);
        $this->assertSame('vw', $dto->brandName);
        $this->assertSame('hatch', $dto->typeName);
        $this->assertSame('#00aaff', $dto->colorHex);
        $this->assertSame('sky', $dto->colorName);
    }

    /**
     * fromArray() should accept legacy key "carregistration".
     *
     *
     * @throws Throwable
     */
    public function test_from_array_accepts_legacy_license_key(): void
    {
        $dto = CarCreateData::fromArray([
            'carregistration' => 'xy-999-zz',
            'model' => ['name' => 'clio'],
            'seats' => 5,
            'brand' => ['name' => 'renault'],
            'type' => ['name' => 'sedan'],
            'color' => ['hex_code' => '#ffffff', 'name' => 'white'],
        ]);

        $this->assertSame('XY-999-ZZ', $dto->licensePlate);
    }

    /**
     * fromArray() should throw when license plate is missing.
     *
     *
     * @throws Throwable
     */
    public function test_from_array_throws_when_license_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CarCreateData::fromArray([
            'model' => ['name' => 'golf'],
            'seats' => 5,
            'brand' => ['name' => 'vw'],
            'type' => ['name' => 'hatch'],
            'color' => ['hex_code' => '#00aaff', 'name' => 'sky'],
        ]);
    }
}

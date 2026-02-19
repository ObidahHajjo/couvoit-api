<?php

namespace Tests\Unit\DTOS;

use App\DTOS\Car\CarUpdateData;
use Tests\TestCase;
use Throwable;

/**
 * Class CarUpdateDataTest
 *
 * Unit tests for CarUpdateData::fromArray mapping and isEmpty().
 */
class CarUpdateDataTest extends TestCase
{
    /**
     * fromArray() should map partial updates and normalize values.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_from_array_maps_partial_and_normalizes(): void
    {
        $dto = CarUpdateData::fromArray([
            'license_plate' => ' ab-123-cd ',
            'model' => ['name' => '  Golf  ', 'seats' => 4],
            'brand' => ['name' => '  VW '],
            'type'  => ['name' => '  Hatch '],
            'color' => ['hex_code' => ' #00AAFF '],
        ]);

        $this->assertSame('AB-123-CD', $dto->licensePlate);
        $this->assertSame('golf', $dto->modelName);
        $this->assertSame(4, $dto->seats);
        $this->assertSame('vw', $dto->brandName);
        $this->assertSame('hatch', $dto->typeName);
        $this->assertSame('#00aaff', $dto->colorHex);
        $this->assertFalse($dto->isEmpty());
    }

    /**
     * isEmpty() should return true when no supported keys are provided.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_is_empty_true_when_no_fields(): void
    {
        $dto = CarUpdateData::fromArray([]);
        $this->assertTrue($dto->isEmpty());
    }
}

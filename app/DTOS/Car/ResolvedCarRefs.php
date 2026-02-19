<?php

namespace App\DTOS\Car;

/**
 * Value object holding resolved foreign key references
 * required to create or update a Car aggregate.
 */
final readonly class ResolvedCarRefs
{
    /**
     * @param int      $brandId
     * @param int      $typeId
     * @param int|null $modelId
     * @param int|null $colorId
     */
    public function __construct(
        public int $brandId,
        public int $typeId,
        public ?int $modelId,
        public ?int $colorId,
    ) {}
}

<?php

namespace App\DTOS\Car;

/**
 * Value object holding resolved foreign key references
 * required to create or update a Car aggregate.
 *
 * @author Covoiturage API Team
 *
 * @description Contains resolved entity IDs for brand, type, model, and color references.
 */
final readonly class ResolvedCarRefs
{
    public function __construct(
        public int $brandId,
        public int $typeId,
        public ?int $modelId,
        public ?int $colorId,
    ) {}
}

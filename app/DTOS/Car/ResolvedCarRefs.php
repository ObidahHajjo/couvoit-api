<?php
namespace App\DTOS\Car;
final readonly class ResolvedCarRefs
{
    public function __construct(
        public int $brandId,
        public int $typeId,
        public ?int $modelId,
        public ?int $colorId,
    ) {}
}

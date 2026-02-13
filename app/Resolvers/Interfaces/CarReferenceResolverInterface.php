<?php

namespace App\Resolvers\Interfaces;

use App\DTOS\Car\ResolvedCarRefs;
use App\Models\Car;

interface CarReferenceResolverInterface
{
    public function resolveForCreate(array $data): ResolvedCarRefs;

    public function resolveModelForUpdate(Car $car, array $data): ?int;
    public function resolveColorForUpdate(array $data): ?int;
}


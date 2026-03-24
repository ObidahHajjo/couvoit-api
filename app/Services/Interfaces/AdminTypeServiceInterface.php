<?php

namespace App\Services\Interfaces;

use App\Models\Type;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AdminTypeServiceInterface
{
    public function listTypes(): Collection;

    public function createType(array $data): Type;

    public function updateType(Type $type, array $data): Type;

    public function deleteType(Type $type): void;
}

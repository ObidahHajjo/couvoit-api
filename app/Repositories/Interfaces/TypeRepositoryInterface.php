<?php

namespace App\Repositories\Interfaces;

use App\Models\Type;
use Illuminate\Support\Collection;

interface TypeRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Type;

    public function createOrFirst(string $name): Type;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function findByType(string $type): ?Type;
}

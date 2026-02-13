<?php

namespace App\Repositories\Interfaces;

use App\Models\Color;
use Illuminate\Support\Collection;

interface ColorRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Color;

    public function createOrFirst(array $data): Color;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function findByName(string $name): ?Color;

    public function findByHexCode(string $hexCode): ?Color;
}

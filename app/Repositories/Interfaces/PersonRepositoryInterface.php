<?php

namespace App\Repositories\Interfaces;

use App\Models\Person;
use Illuminate\Support\Collection;

interface PersonRepositoryInterface
{
    public function all();

    public function findById(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function attachCar(Person $person, int $carId): bool;

    public function findBySupabaseUserId(string $supabaseUserId): ?Person;

    public function updateRole(string $supabaseUserId, int $roleId): void;

}

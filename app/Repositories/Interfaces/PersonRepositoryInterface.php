<?php

namespace App\Repositories\Interfaces;

use App\Models\Person;
use Illuminate\Support\Collection;

interface PersonRepositoryInterface
{
    /**
     * Retrieve all persons.
     *
     * @return Collection<int,Person>
     */
    public function all(): Collection;

    /**
     * Find a person by id (or fail).
     *
     * @param int $id
     * @return Person
     */
    public function findById(int $id): Person;

    /**
     * Persist a new person and update caches.
     *
     * @param array<string,mixed> $data
     * @return Person
     */
    public function create(array $data): Person;

    /**
     * Update a person by id and refresh caches.
     *
     * Note: role_id is explicitly ignored here (use updateRole()).
     *
     * @param int $id
     * @param array<string,mixed> $data
     * @return void
     */
    public function update(int $id, array $data): void;

    /**
     * Soft-delete a person by id and invalidate caches.
     *
     * @param int $id
     * @return void
     */
    public function delete(int $id): void;

    /**
     * Attach a car to the given person and refresh caches.
     *
     * @param Person $person
     * @param int $carId
     * @return bool
     */
    public function attachCar(Person $person, int $carId): bool;

    /**
     * Find an active person by Supabase user id.
     *
     * @param string $supabaseUserId
     * @return Person|null
     */
    public function findBySupabaseUserId(string $supabaseUserId): ?Person;

    /**
     * Update role for a person identified by Supabase user id and refresh caches.
     *
     * @param string $supabaseUserId
     * @param int $roleId
     * @return void
     */
    public function updateRole(string $supabaseUserId, int $roleId): void;

}

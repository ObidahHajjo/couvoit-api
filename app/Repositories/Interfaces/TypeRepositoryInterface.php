<?php

namespace App\Repositories\Interfaces;

use App\Models\Type;
use Illuminate\Support\Collection;

interface TypeRepositoryInterface
{
    /**
     * Retrieve all types ordered by type value.
     *
     * @return Collection<int,Type>
     */
    public function all(): Collection;

    /**
     * Find a type by its identifier.
     *
     * @param int $id
     * @return Type|null
     */
    public function findById(int $id): ?Type;

    /**
     * Create a type if it does not exist (by unique "type") or return the existing one.
     *
     * Updates caches for id/value and invalidates the global list.
     *
     * @param string $name
     * @return Type
     */
    public function createOrFirst(string $name): Type;

    /**
     * Update a type by id and refresh caches.
     *
     * @param int                $id
     * @param array<string,mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a type by id and invalidate caches.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find a type by its (case-insensitive) value.
     *
     * @param string $type
     * @return Type|null
     */
    public function findByType(string $type): ?Type;
}

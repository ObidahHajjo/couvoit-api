<?php

namespace App\Services\Interfaces;

use App\Models\Type;
use Illuminate\Support\Collection;

/**
 * Contract for admin type management services.
 */
interface AdminTypeServiceInterface
{
    /**
     * List all types.
     *
     * @return Collection<int, Type>
     */
    public function listTypes(): Collection;

    /**
     * Create a new type.
     *
     * @param  array<string, mixed>  $data  Type creation data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function createType(array $data): Type;

    /**
     * Update an existing type.
     *
     * @param  Type  $type  Type to update.
     * @param  array<string, mixed>  $data  Type update data.
     *
     * @throws \Throwable If the operation fails.
     */
    public function updateType(Type $type, array $data): Type;

    /**
     * Delete a type.
     *
     * @param  Type  $type  Type to delete.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteType(Type $type): void;
}

<?php

/**
 * @author Admin
 *
 * @description Service implementation for managing type operations in the admin panel.
 */

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\CarModel;
use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use App\Services\Interfaces\AdminTypeServiceInterface;
use Illuminate\Support\Collection;

/**
 * Service implementation for admin type management operations.
 */
readonly class AdminTypeService implements AdminTypeServiceInterface
{
    public function __construct(
        private TypeRepositoryInterface $types,
    ) {}

    /**
     * List all types.
     *
     * @return Collection Collection of all types
     */
    public function listTypes(): Collection
    {
        return $this->types->all();
    }

    /**
     * Create a new type or return existing one.
     *
     * @param  array  $data  Type data including the type name
     * @return Type The created or existing type instance
     */
    public function createType(array $data): Type
    {
        return $this->types->createOrFirst($data['type']);
    }

    /**
     * Update an existing type.
     *
     * @param  Type  $type  The type to update
     * @param  array  $data  Updated type data
     * @return Type The updated type instance
     */
    public function updateType(Type $type, array $data): Type
    {
        $this->types->update($type->id, $data);

        return $this->types->findById($type->id) ?? $type;
    }

    /**
     * Delete a type.
     *
     * @param  Type  $type  The type to delete
     *
     * @throws ConflictException When the type has associated car models
     */
    public function deleteType(Type $type): void
    {
        if ($this->hasCarModels($type)) {
            throw new ConflictException('Cannot delete type assigned to existing car models.');
        }

        $this->types->delete($type->id);
    }

    /**
     * Check if the type has associated car models.
     *
     * @param  Type  $type  The type to check
     * @return bool True if type has car models, false otherwise
     */
    private function hasCarModels(Type $type): bool
    {
        return CarModel::query()->where('type_id', $type->id)->exists();
    }
}

<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use App\Services\Interfaces\AdminTypeServiceInterface;
use Illuminate\Support\Collection;

readonly class AdminTypeService implements AdminTypeServiceInterface
{
    public function __construct(
        private TypeRepositoryInterface $types,
    ) {}

    public function listTypes(): Collection
    {
        return $this->types->all();
    }

    public function createType(array $data): Type
    {
        return $this->types->createOrFirst($data['type']);
    }

    public function updateType(Type $type, array $data): Type
    {
        $this->types->update($type->id, $data);

        return $this->types->findById($type->id) ?? $type;
    }

    public function deleteType(Type $type): void
    {
        if ($this->hasCarModels($type)) {
            throw new ConflictException('Cannot delete type assigned to existing car models.');
        }

        $this->types->delete($type->id);
    }

    private function hasCarModels(Type $type): bool
    {
        return \App\Models\CarModel::query()->where('type_id', $type->id)->exists();
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Support\Collection;

class TypeEloquentRepository implements TypeRepositoryInterface
{
    public function all(): Collection
    {
        return Type::query()->get();
    }

    public function findById(int $id): ?Type
    {
        return Type::query()->find($id);
    }

    public function createOrFirst(string $name): Type
    {
        return Type::query()->createOrFirst(
            ["type" =>$name],
            ["type" => $name]
        );
    }

    public function update(int $id, array $data): bool
    {
        $type = Type::query()->find($id);

        if (!$type) {
            return false;
        }

        return $type->update($data);
    }

    public function delete(int $id): bool
    {
        $type = Type::query()->find($id);

        if (!$type) {
            return false;
        }

        return (bool) $type->delete();
    }

    public function findByType(string $type): ?Type
    {
        return Type::query()
            ->where('type', $type)
            ->first();
    }
}

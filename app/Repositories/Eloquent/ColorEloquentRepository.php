<?php

namespace App\Repositories\Eloquent;

use App\Models\Color;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use Illuminate\Support\Collection;

class ColorEloquentRepository implements ColorRepositoryInterface
{
    public function all(): Collection
    {
        return Color::query()->get();
    }

    public function findById(int $id): ?Color
    {
        return Color::query()->find($id);
    }

    public function createOrFirst(array $data): Color
    {
        return Color::query()->createOrFirst(
            ["hex_code" => $data["hex_code"]],
            $data
        );
    }

    public function update(int $id, array $data): bool
    {
        $color = Color::query()->find($id);

        if (!$color) {
            return false;
        }

        return $color->update($data);
    }

    public function delete(int $id): bool
    {
        $color = Color::query()->find($id);

        if (!$color) {
            return false;
        }

        return (bool) $color->delete();
    }

    public function findByName(string $name): ?Color
    {
        return Color::query()
            ->where('name', $name)
            ->first();
    }

    public function findByHexCode(string $hexCode): ?Color
    {
        return Color::query()
            ->where('hex_code', $hexCode)
            ->first();
    }
}

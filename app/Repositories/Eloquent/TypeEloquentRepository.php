<?php

namespace App\Repositories\Eloquent;

use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TypeEloquentRepository implements TypeRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function keyAll(): string { return 'types:all'; }
    private function keyById(int $id): string { return "types:$id"; }
    private function keyByType(string $type): string { return 'types:type:' . mb_strtolower(trim($type)); }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return Type::query()->get();
        });

        return $cached;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Type
    {
        return Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Type::query()->find($id);
        });
    }

    /** @inheritDoc */
    public function createOrFirst(string $name): Type
    {
        $name = mb_strtolower(trim($name));

        $type = Type::query()->createOrFirst(
            ['type' => $name],
            ['type' => $name]
        );

        Cache::put($this->keyById((int) $type->id), $type, self::TTL_SECONDS);
        Cache::put($this->keyByType((string) $type->type), $type, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $type;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): bool
    {
        $type = Type::query()->find($id);
        if (! $type) return false;

        $oldType = (string) $type->type;

        $ok = $type->update($data);
        $type->refresh();

        Cache::put($this->keyById($id), $type, self::TTL_SECONDS);
        Cache::forget($this->keyByType($oldType));
        Cache::put($this->keyByType((string) $type->type), $type, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $type = Type::query()->find($id);
        if (! $type) return false;

        $typeValue = (string) $type->type;

        $ok = (bool) $type->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyByType($typeValue));
        Cache::forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findByType(string $type): ?Type
    {
        $key = $this->keyByType($type);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($type) {
            return Type::query()->where('type', $type)->first();
        });
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of TypeRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - Global list: types:all (tag: types)
 * - By id:       types:{id} (tags: types, type_id:{id})
 * - By value:    types:type:{normalizedType} (tags: types, type:{normalizedType})
 */
class TypeEloquentRepository implements TypeRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    // ---------- Tags ----------

    /**
     * @return array<int,string>
     */
    private function tagTypes(): array
    {
        return ['types'];
    }

    /**
     * @param int $id
     * @return array<int,string>
     */
    private function tagTypeId(int $id): array
    {
        return ['types', 'type_id:' . $id];
    }

    /**
     * @param string $type
     * @return array<int,string>
     */
    private function tagTypeValue(string $type): array
    {
        return ['types', 'type:' . $this->normalizeType($type)];
    }

    // ---------- Keys ----------

    private function keyAll(): string
    {
        return 'types:all';
    }

    private function keyById(int $id): string
    {
        return 'types:' . $id;
    }

    private function keyByType(string $type): string
    {
        return 'types:type:' . $this->normalizeType($type);
    }

    /**
     * Normalize type value for consistent caching and lookup.
     *
     * @param string $type
     * @return string
     */
    private function normalizeType(string $type): string
    {
        return mb_strtolower(trim($type));
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Type> $types */
        $types = Cache::tags($this->tagTypes())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Type::query()
                    ->orderBy('type')
                    ->get();
            });

        // Optional warming
        foreach ($types as $t) {
            Cache::tags($this->tagTypeId($t->id))
                ->put($this->keyById($t->id), $t, self::TTL_SECONDS);

            Cache::tags($this->tagTypeValue($t->type))
                ->put($this->keyByType($t->type), $t, self::TTL_SECONDS);
        }

        return $types;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Type
    {
        /** @var Type|null $type */
        $type = Cache::tags($this->tagTypeId($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Type::query()->find($id);
            });

        return $type;
    }

    /** @inheritDoc */
    public function createOrFirst(string $name): Type
    {
        $name = $this->normalizeType($name);

        $type = Type::query()->createOrFirst(
            ['type' => $name],
            ['type' => $name]
        );

        Cache::tags($this->tagTypeId((int) $type->id))
            ->put($this->keyById((int) $type->id), $type, self::TTL_SECONDS);

        Cache::tags($this->tagTypeValue((string) $type->type))
            ->put($this->keyByType((string) $type->type), $type, self::TTL_SECONDS);

        Cache::tags($this->tagTypes())->forget($this->keyAll());

        return $type;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): bool
    {
        $type = Type::query()->find($id);
        if (! $type) {
            return false;
        }

        $oldType = (string) $type->type;

        if (array_key_exists('type', $data)) {
            $data['type'] = $this->normalizeType((string) $data['type']);
        }

        $ok = $type->update($data);
        $type->refresh();

        Cache::tags($this->tagTypeId($id))
            ->put($this->keyById($id), $type, self::TTL_SECONDS);

        if ($oldType !== (string) $type->type) {
            Cache::tags($this->tagTypeValue($oldType))->flush();
        }

        Cache::tags($this->tagTypeValue((string) $type->type))
            ->put($this->keyByType((string) $type->type), $type, self::TTL_SECONDS);

        Cache::tags($this->tagTypes())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $type = Type::query()->find($id);
        if (! $type) {
            return false;
        }

        $typeValue = (string) $type->type;

        $ok = (bool) $type->delete();

        Cache::tags($this->tagTypeId($id))->flush();
        Cache::tags($this->tagTypeValue($typeValue))->flush();
        Cache::tags($this->tagTypes())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findByType(string $type): ?Type
    {
        /** @var Type|null $t */
        $t = Cache::tags($this->tagTypeValue($type))
            ->remember($this->keyByType($type), self::TTL_SECONDS, function () use ($type) {
                $normalized = $this->normalizeType($type);

                return Type::query()
                    ->whereRaw('lower(type) = ?', [$normalized])
                    ->first();
            });

        if ($t) {
            Cache::tags($this->tagTypeId($t->id))
                ->put($this->keyById($t->id), $t, self::TTL_SECONDS);
        }

        return $t;
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Type;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

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
readonly class TypeEloquentRepository implements TypeRepositoryInterface
{
    /**
     * Create a new type repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
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
        $types = $this->cache->rememberTypesAll(function () {
            return Type::query()
                ->orderBy('type')
                ->get();
        });

        foreach ($types as $type) {
            $this->cache->putType($type, $type->type);
        }

        return $types;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Type
    {
        /** @var Type|null $type */
        $type = $this->cache->rememberTypeById($id, fn () => Type::query()->find($id));

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

        $this->cache->putType($type, (string) $type->type);
        $this->cache->forgetTypesAll();
        $this->cache->invalidateCarsAndPersonsByTypeId((int) $type->id);

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

        $this->cache->putType($type, (string) $type->type);

        if ($oldType !== (string) $type->type) {
            $this->cache->forgetTypeByValue($oldType);
        }

        $this->cache->forgetTypesAll();
        $this->cache->invalidateCarsAndPersonsByTypeId($id);

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

        $this->cache->forgetType($id);
        $this->cache->forgetTypeByValue($typeValue);
        $this->cache->forgetTypesAll();
        $this->cache->invalidateCarsAndPersonsByTypeId($id);

        return $ok;
    }

    /** @inheritDoc */
    public function findByType(string $type): ?Type
    {
        /** @var Type|null $found */
        $found = $this->cache->rememberTypeByValue($type, function () use ($type) {
            $normalized = $this->normalizeType($type);

            return Type::query()
                ->whereRaw('lower(type) = ?', [$normalized])
                ->first();
        });

        if ($found) {
            $this->cache->putType($found, $found->type);
        }

        return $found;
    }
}

<?php

/**
 * @author    [Developer Name]
 *
 * @description Eloquent implementation of TypeRepositoryInterface for managing Type entities.
 */

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
 *
 * @implements TypeRepositoryInterface
 */
readonly class TypeEloquentRepository implements TypeRepositoryInterface
{
    /**
     * Create a new type repository instance.
     *
     * @param  RepositoryCacheManager  $cache  The cache manager for caching type data.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Normalize type value for consistent caching and lookup.
     *
     * @param  string  $type  The type value to normalize.
     * @return string The normalized type value (lowercase, trimmed).
     */
    private function normalizeType(string $type): string
    {
        return mb_strtolower(trim($type));
    }

    /**
     * Retrieve all types.
     *
     * @return Collection<int, Type> Collection of all Type entities.
     */
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

    /**
     * Find a type by its ID.
     *
     * @param  int  $id  The ID of the type to retrieve.
     * @return Type|null The Type entity if found, null otherwise.
     */
    public function findById(int $id): ?Type
    {
        /** @var Type|null $type */
        $type = $this->cache->rememberTypeById($id, fn () => Type::query()->find($id));

        return $type;
    }

    /**
     * Create a new type or find an existing one by name.
     *
     * @param  string  $name  The name of the type to create or find.
     * @return Type The created or existing Type entity.
     */
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

    /**
     * Update an existing type record.
     *
     * @param  int  $id  The ID of the type to update.
     * @param  array  $data  The data to update the type with.
     * @return bool True if the update was successful, false if type not found.
     */
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

    /**
     * Delete a type record.
     *
     * @param  int  $id  The ID of the type to delete.
     * @return bool True if the deletion was successful, false if type not found.
     */
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

    /**
     * Find a type by its type value.
     *
     * @param  string  $type  The type value to search for.
     * @return Type|null The Type entity if found, null otherwise.
     */
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

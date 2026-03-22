<?php

namespace App\Repositories\Interfaces;

use App\Models\Color;
use Illuminate\Support\Collection;

/**
 * Contract for color persistence operations.
 */
interface ColorRepositoryInterface
{
    /**
     * Retrieve all colors ordered by name.
     *
     * @return Collection<int,Color>
     */
    public function all(): Collection;

    /**
     * Find a color by its identifier.
     *
     * @param int $id
     * @return Color|null
     */
    public function findById(int $id): ?Color;

    /**
     * Create a color if it does not exist (by unique hex_code) or return the existing one.
     *
     * Updates caches for id/hex/name and invalidates the global list.
     *
     * @param array<string,mixed> $data
     * @return Color
     */
    public function createOrFirst(array $data): Color;

    /**
     * Update the given color by id and refresh caches.
     *
     * @param int                $id
     * @param array<string,mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete the given color by id and invalidate caches.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find a color by its (case-insensitive) name.
     *
     * @param string $name
     * @return Color|null
     */
    public function findByName(string $name): ?Color;

    /**
     * Find a color by its (case-insensitive) hex code.
     *
     * @param string $hexCode
     * @return Color|null
     */
    public function findByHexCode(string $hexCode): ?Color;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Color;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of ColorRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - Global list: colors:all (tag: colors)
 * - By id:       colors:{id} (tags: colors, color:{id})
 * - By hex:      colors:hex:{normalizedHex} (tags: colors, hex:{normalizedHex})
 * - By name:     colors:name:{normalizedName} (tags: colors, name:{normalizedName})
 */
readonly class ColorEloquentRepository implements ColorRepositoryInterface
{
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    private function normalizeHex(string $hex): string
    {
        return mb_strtolower(trim($hex));
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Color> $colors */
        $colors = $this->cache->rememberColorsAll(function () {
            return Color::query()
                ->orderBy('name')
                ->get();
        });

        foreach ($colors as $color) {
            $this->cache->putColor($color, $color->name, $color->hex_code);
        }

        return $colors;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Color
    {
        /** @var Color|null $color */
        $color = $this->cache->rememberColorById($id, fn () => Color::query()->find($id));

        return $color;
    }

    /** @inheritDoc */
    public function createOrFirst(array $data): Color
    {
        if (isset($data['hex_code'])) {
            $data['hex_code'] = $this->normalizeHex((string) $data['hex_code']);
        }

        if (isset($data['name'])) {
            $data['name'] = $this->normalizeName((string) $data['name']);
        }

        $color = Color::query()->createOrFirst(
            ['hex_code' => $data['hex_code']],
            $data
        );

        $color->refresh();

        $this->cache->putColor($color, (string) $color->name, (string) $color->hex_code);
        $this->cache->forgetColorsAll();
        $this->cache->invalidateCarsAndPersonsByColorId((int) $color->id);

        return $color;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): bool
    {
        $color = Color::query()->find($id);
        if (! $color) {
            return false;
        }

        $oldHex = (string) $color->hex_code;
        $oldName = (string) $color->name;

        if (isset($data['hex_code'])) {
            $data['hex_code'] = $this->normalizeHex((string) $data['hex_code']);
        }

        if (isset($data['name'])) {
            $data['name'] = $this->normalizeName((string) $data['name']);
        }

        $ok = $color->update($data);
        $color->refresh();

        $this->cache->putColor($color, (string) $color->name, (string) $color->hex_code);

        if ($oldHex !== (string) $color->hex_code) {
            $this->cache->forgetColorByHex($oldHex);
        }

        if ($oldName !== (string) $color->name) {
            $this->cache->forgetColorByName($oldName);
        }

        $this->cache->forgetColorsAll();
        $this->cache->invalidateCarsAndPersonsByColorId($id);

        return $ok;
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $color = Color::query()->find($id);
        if (! $color) {
            return false;
        }

        $hex = (string) $color->hex_code;
        $name = (string) $color->name;

        $ok = (bool) $color->delete();

        $this->cache->forgetColor($id);
        $this->cache->forgetColorByHex($hex);
        $this->cache->forgetColorByName($name);
        $this->cache->forgetColorsAll();
        $this->cache->invalidateCarsAndPersonsByColorId($id);

        return $ok;
    }

    /** @inheritDoc */
    public function findByName(string $name): ?Color
    {
        /** @var Color|null $color */
        $color = $this->cache->rememberColorByName($name, function () use ($name) {
            $normalized = $this->normalizeName($name);

            return Color::query()
                ->whereRaw('lower(name) = ?', [$normalized])
                ->first();
        });

        if ($color) {
            $this->cache->putColor($color, $color->name, $color->hex_code);
        }

        return $color;
    }

    /** @inheritDoc */
    public function findByHexCode(string $hexCode): ?Color
    {
        /** @var Color|null $color */
        $color = $this->cache->rememberColorByHex($hexCode, function () use ($hexCode) {
            $normalized = $this->normalizeHex($hexCode);

            return Color::query()
                ->whereRaw('lower(hex_code) = ?', [$normalized])
                ->first();
        });

        if ($color) {
            $this->cache->putColor($color, $color->name, $color->hex_code);
        }

        return $color;
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Color;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ColorEloquentRepository implements ColorRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    // ---------- Tags ----------
    private function tagColors(): array { return ['colors']; }
    private function tagColor(int $id): array { return ['colors', 'color:' . $id]; }
    private function tagHex(string $hex): array { return ['colors', 'hex:' . $this->normalizeHex($hex)]; }
    private function tagName(string $name): array { return ['colors', 'name:' . $this->normalizeName($name)]; }

    // ---------- Keys ----------
    private function keyAll(): string { return 'colors:all'; }
    private function keyById(int $id): string { return 'colors:' . $id; }
    private function keyByHex(string $hex): string { return 'colors:hex:' . $this->normalizeHex($hex); }
    private function keyByName(string $name): string { return 'colors:name:' . $this->normalizeName($name); }

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
        $colors = Cache::tags($this->tagColors())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Color::query()->orderBy('name')->get();
            });

        // Optional warming: id + hex + name
        foreach ($colors as $c) {
            Cache::tags($this->tagColor($c->id))
                ->put($this->keyById($c->id), $c, self::TTL_SECONDS);

            Cache::tags($this->tagHex($c->hex_code))
                ->put($this->keyByHex($c->hex_code), $c, self::TTL_SECONDS);

            Cache::tags($this->tagName($c->name))
                ->put($this->keyByName($c->name), $c, self::TTL_SECONDS);
        }

        return $colors;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Color
    {
        /** @var Color|null $color */
        $color = Cache::tags($this->tagColor($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Color::query()->find($id);
            });

        return $color;
    }

    /** @inheritDoc */
    public function createOrFirst(array $data): Color
    {
        if (isset($data['hex_code'])) $data['hex_code'] = $this->normalizeHex((string) $data['hex_code']);
        if (isset($data['name'])) $data['name'] = $this->normalizeName((string) $data['name']);

        $color = Color::query()->createOrFirst(
            ['hex_code' => $data['hex_code']],
            $data
        );

        $color->refresh();

        Cache::tags($this->tagColor($color->id))
            ->put($this->keyById($color->id), $color, self::TTL_SECONDS);

        Cache::tags($this->tagHex($color->hex_code))
            ->put($this->keyByHex($color->hex_code), $color, self::TTL_SECONDS);

        Cache::tags($this->tagName($color->name))
            ->put($this->keyByName($color->name), $color, self::TTL_SECONDS);

        Cache::tags($this->tagColors())->forget($this->keyAll());

        return $color;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): bool
    {
        $color = Color::query()->find($id);
        if (!$color) return false;

        $oldHex = (string) $color->hex_code;
        $oldName = (string) $color->name;

        if (isset($data['hex_code'])) $data['hex_code'] = $this->normalizeHex((string) $data['hex_code']);
        if (isset($data['name'])) $data['name'] = $this->normalizeName((string) $data['name']);

        $ok = $color->update($data);
        $color->refresh();

        Cache::tags($this->tagColor($id))
            ->put($this->keyById($id), $color, self::TTL_SECONDS);

        if ($oldHex !== (string) $color->hex_code) {
            Cache::tags($this->tagHex($oldHex))->flush();
        }
        if ($oldName !== (string) $color->name) {
            Cache::tags($this->tagName($oldName))->flush();
        }

        Cache::tags($this->tagHex($color->hex_code))
            ->put($this->keyByHex($color->hex_code), $color, self::TTL_SECONDS);

        Cache::tags($this->tagName($color->name))
            ->put($this->keyByName($color->name), $color, self::TTL_SECONDS);

        Cache::tags($this->tagColors())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $color = Color::query()->find($id);
        if (!$color) return false;

        $hex = (string) $color->hex_code;
        $name = (string) $color->name;

        $ok = (bool) $color->delete();

        Cache::tags($this->tagColor($id))->flush();
        Cache::tags($this->tagHex($hex))->flush();
        Cache::tags($this->tagName($name))->flush();
        Cache::tags($this->tagColors())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findByName(string $name): ?Color
    {
        $key = $this->keyByName($name);

        /** @var Color|null $color */
        $color = Cache::tags($this->tagName($name))
            ->remember($key, self::TTL_SECONDS, function () use ($name) {
                $normalized = $this->normalizeName($name);

                return Color::query()
                    ->whereRaw('lower(name) = ?', [$normalized])
                    ->first();
            });

        if ($color) {
            Cache::tags($this->tagColor($color->id))
                ->put($this->keyById($color->id), $color, self::TTL_SECONDS);

            Cache::tags($this->tagHex($color->hex_code))
                ->put($this->keyByHex($color->hex_code), $color, self::TTL_SECONDS);
        }

        return $color;
    }

    /** @inheritDoc */
    public function findByHexCode(string $hexCode): ?Color
    {
        $key = $this->keyByHex($hexCode);

        /** @var Color|null $color */
        $color = Cache::tags($this->tagHex($hexCode))
            ->remember($key, self::TTL_SECONDS, function () use ($hexCode) {
                $normalized = $this->normalizeHex($hexCode);

                return Color::query()
                    ->whereRaw('lower(hex_code) = ?', [$normalized])
                    ->first();
            });

        if ($color) {
            Cache::tags($this->tagColor($color->id))
                ->put($this->keyById($color->id), $color, self::TTL_SECONDS);

            Cache::tags($this->tagName($color->name))
                ->put($this->keyByName($color->name), $color, self::TTL_SECONDS);
        }

        return $color;
    }
}

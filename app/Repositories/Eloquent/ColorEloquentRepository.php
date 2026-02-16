<?php

namespace App\Repositories\Eloquent;

use App\Models\Color;
use App\Repositories\Interfaces\ColorRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ColorEloquentRepository implements ColorRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    private function keyAll(): string { return 'colors:all'; }
    private function keyById(int $id): string { return "colors:{$id}"; }
    private function keyByHex(string $hex): string { return 'colors:hex:' . mb_strtolower(trim($hex)); }
    private function keyByName(string $name): string { return 'colors:name:' . mb_strtolower(trim($name)); }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var \Illuminate\Support\Collection $cached */
        $cached = Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return Color::query()->get();
        });

        return $cached;
    }

    /** @inheritDoc */
    public function findById(int $id): ?Color
    {
        return Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Color::query()->find($id);
        });
    }

    /** @inheritDoc */
    public function createOrFirst(array $data): Color
    {
        $color = Color::query()->createOrFirst(
            ['hex_code' => $data['hex_code']],
            $data
        );

        $color->refresh();

        Cache::put($this->keyById((int) $color->id), $color, self::TTL_SECONDS);
        Cache::put($this->keyByHex((string) $color->hex_code), $color, self::TTL_SECONDS);
        Cache::put($this->keyByName((string) $color->name), $color, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $color;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): bool
    {
        $color = Color::query()->find($id);
        if (! $color) return false;

        $oldHex = (string) $color->hex_code;
        $oldName = (string) $color->name;

        $ok = $color->update($data);
        $color->refresh();

        Cache::put($this->keyById($id), $color, self::TTL_SECONDS);
        Cache::forget($this->keyByHex($oldHex));
        Cache::forget($this->keyByName($oldName));
        Cache::put($this->keyByHex((string) $color->hex_code), $color, self::TTL_SECONDS);
        Cache::put($this->keyByName((string) $color->name), $color, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function delete(int $id): bool
    {
        $color = Color::query()->find($id);
        if (! $color) return false;

        $hex = (string) $color->hex_code;
        $name = (string) $color->name;

        $ok = (bool) $color->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyByHex($hex));
        Cache::forget($this->keyByName($name));
        Cache::forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findByName(string $name): ?Color
    {
        $key = $this->keyByName($name);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($name) {
            return Color::query()->where('name', $name)->first();
        });
    }

    /** @inheritDoc */
    public function findByHexCode(string $hexCode): ?Color
    {
        $key = $this->keyByHex($hexCode);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($hexCode) {
            return Color::query()->where('hex_code', $hexCode)->first();
        });
    }
}

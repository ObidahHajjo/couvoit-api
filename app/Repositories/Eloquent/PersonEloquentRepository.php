<?php

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PersonEloquentRepository implements PersonRepositoryInterface
{
    private const TTL_SECONDS = 600;

    private function keyAll(): string { return 'persons:all'; }
    private function keyById(int $id): string { return "persons:$id"; }
    private function keyBySupabase(string $uuid): string { return "persons:supabase:$uuid"; }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection $cached */
        $cached = Cache::remember($this->keyAll(), self::TTL_SECONDS, function () {
            return Person::query()->with(['role', 'car'])->get();
        });

        return $cached;
    }

    /** @inheritDoc */
    public function findById(int $id): Person
    {
        /** @var Person $person */
        $person = Cache::remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
            return Person::query()->with(['role', 'car'])->findOrFail($id);
        });

        // also warm supabase cache
        Cache::put($this->keyBySupabase($person->supabase_user_id), $person, self::TTL_SECONDS);

        return $person;
    }

    /** @inheritDoc */
    public function create(array $data): Person
    {
        $person = Person::query()->create($data)->loadMissing(['role', 'car']);

        Cache::put($this->keyById((int) $person->id), $person, self::TTL_SECONDS);
        Cache::put($this->keyBySupabase((string) $person->supabase_user_id), $person, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $person;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): void
    {
        $person = Person::query()->with(['role', 'car'])->findOrFail($id);
        $oldSupabase = (string) $person->supabase_user_id;

        $person->update($data);
        $person->refresh()->loadMissing(['role', 'car']);

        Cache::put($this->keyById($id), $person, self::TTL_SECONDS);
        Cache::forget($this->keyBySupabase($oldSupabase));
        Cache::put($this->keyBySupabase((string) $person->supabase_user_id), $person, self::TTL_SECONDS);
        Cache::forget($this->keyAll());
    }

    /** @inheritDoc */
    public function delete(int $id): void
    {
        $person = Person::query()->findOrFail($id);
        $supabase = (string) $person->supabase_user_id;

        $person->delete();

        Cache::forget($this->keyById($id));
        Cache::forget($this->keyBySupabase($supabase));
        Cache::forget($this->keyAll());
    }

    /** @inheritDoc */
    public function attachCar(Person $person, int $carId): bool
    {
        $person->car_id = $carId;
        $ok = $person->save();

        $person->refresh()->loadMissing(['role', 'car']);

        Cache::put($this->keyById($person->id), $person, self::TTL_SECONDS);
        Cache::put($this->keyBySupabase($person->supabase_user_id), $person, self::TTL_SECONDS);
        Cache::forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findBySupabaseUserId(string $supabaseUserId): ?Person
    {
        return Cache::remember($this->keyBySupabase($supabaseUserId), self::TTL_SECONDS, function () use ($supabaseUserId) {
            return Person::query()->with(['role', 'car'])
                ->where('supabase_user_id', $supabaseUserId)
                ->first();
        });
    }
}

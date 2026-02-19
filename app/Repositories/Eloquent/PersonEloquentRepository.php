<?php

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of PersonRepositoryInterface.
 *
 * Provides read-through and write-through caching using tagged cache.
 *
 * Cache strategy:
 * - Global list: persons:all (tag: persons)
 * - By id:       persons:{id} (tags: persons, person:{id})
 * - By supabase: persons:supabase:{uuid} (tags: persons, supabase:{uuid})
 */
class PersonEloquentRepository implements PersonRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    /**
     * @return array<int,string>
     */
    private function tagPersons(): array
    {
        return ['persons'];
    }

    /**
     * @param int $id
     * @return array<int,string>
     */
    private function tagPerson(int $id): array
    {
        return ['persons', "person:$id"];
    }

    /**
     * @param string $uuid
     * @return array<int,string>
     */
    private function tagSupabase(string $uuid): array
    {
        return ['persons', "supabase:$uuid"];
    }

    private function keyAll(): string
    {
        return 'persons:all';
    }

    private function keyById(int $id): string
    {
        return "persons:$id";
    }

    private function keyBySupabase(string $uuid): string
    {
        return "persons:supabase:$uuid";
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        /** @var Collection<int,Person> $people */
        $people = Cache::tags($this->tagPersons())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Person::query()
                    ->with(['role', 'car'])
                    ->get();
            });

        // Optional: warm per-person caches so findById/findBySupabaseUserId can hit cache
        foreach ($people as $p) {
            Cache::tags($this->tagPerson($p->id))
                ->put($this->keyById($p->id), $p, self::TTL_SECONDS);

            if ($p->supabase_user_id) {
                Cache::tags($this->tagSupabase($p->supabase_user_id))
                    ->put($this->keyBySupabase($p->supabase_user_id), $p, self::TTL_SECONDS);
            }
        }

        return $people;
    }

    /** @inheritDoc */
    public function findById(int $id): Person
    {
        /** @var Person $person */
        $person = Cache::tags($this->tagPerson($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Person::query()
                    ->with(['role', 'car'])
                    ->findOrFail($id);
            });

        // Warm supabase cache if available
        if ($person->supabase_user_id) {
            Cache::tags($this->tagSupabase($person->supabase_user_id))
                ->put($this->keyBySupabase($person->supabase_user_id), $person, self::TTL_SECONDS);
        }

        return $person;
    }

    /** @inheritDoc */
    public function create(array $data): Person
    {
        $person = Person::query()
            ->create($data)
            ->loadMissing(['role', 'car']);

        Cache::tags($this->tagPerson((int) $person->id))
            ->put($this->keyById((int) $person->id), $person, self::TTL_SECONDS);

        if ($person->supabase_user_id) {
            Cache::tags($this->tagSupabase((string) $person->supabase_user_id))
                ->put($this->keyBySupabase((string) $person->supabase_user_id), $person, self::TTL_SECONDS);
        }

        Cache::tags($this->tagPersons())->forget($this->keyAll());

        return $person;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): void
    {
        if (array_key_exists('role_id', $data)) {
            unset($data['role_id']);
        }

        $person = $this->findById($id);
        $oldSupabase = $person->supabase_user_id;

        $person->update($data);
        $person->refresh()->loadMissing(['role', 'car']);

        // Invalidate old supabase tag if changed
        if ($oldSupabase !== '' && $oldSupabase !== $person->supabase_user_id) {
            Cache::tags($this->tagSupabase($oldSupabase))->flush();
        }

        Cache::tags($this->tagPerson($id))
            ->put($this->keyById($id), $person, self::TTL_SECONDS);

        if ($person->supabase_user_id) {
            Cache::tags($this->tagSupabase($person->supabase_user_id))
                ->put($this->keyBySupabase($person->supabase_user_id), $person, self::TTL_SECONDS);
        }

        Cache::tags($this->tagPersons())->forget($this->keyAll());
    }

    /** @inheritDoc */
    public function delete(int $id): void
    {
        $person = Person::query()->findOrFail($id);
        $supabase = (string) $person->supabase_user_id;

        $person->delete();

        Cache::tags($this->tagPerson($id))->flush();

        if ($supabase !== '') {
            Cache::tags($this->tagSupabase($supabase))->flush();
        }

        Cache::tags($this->tagPersons())->forget($this->keyAll());
    }

    /** @inheritDoc */
    public function attachCar(Person $person, int $carId): bool
    {
        $person->car_id = $carId;
        $ok = $person->save();

        $person->refresh()->loadMissing(['role', 'car']);

        Cache::tags($this->tagPerson($person->id))
            ->put($this->keyById($person->id), $person, self::TTL_SECONDS);

        if ($person->supabase_user_id) {
            Cache::tags($this->tagSupabase($person->supabase_user_id))
                ->put($this->keyBySupabase($person->supabase_user_id), $person, self::TTL_SECONDS);
        }

        Cache::tags($this->tagPersons())->forget($this->keyAll());

        return $ok;
    }

    /** @inheritDoc */
    public function findBySupabaseUserId(string $supabaseUserId): ?Person
    {
        /** @var Person|null $person */
        $person = Cache::tags($this->tagSupabase($supabaseUserId))
            ->remember($this->keyBySupabase($supabaseUserId), self::TTL_SECONDS, function () use ($supabaseUserId) {
                return Person::query()
                    ->with(['role', 'car'])
                    ->where('supabase_user_id', $supabaseUserId)
                    ->where('is_active', true)
                    ->first();
            });

        // Warm id cache
        if ($person) {
            Cache::tags($this->tagPerson($person->id))
                ->put($this->keyById($person->id), $person, self::TTL_SECONDS);
        }

        return $person;
    }

    /** @inheritDoc */
    public function updateRole(string $supabaseUserId, int $roleId): void
    {
        /** @var Person|null $person */
        $person = $this->findBySupabaseUserId($supabaseUserId);

        // Keep behavior consistent with existing implementation: will error if not found.
        /** @var Person $person */
        $person->role_id = $roleId;
        $person->save();

        $person->refresh()->loadMissing(['role', 'car']);

        Cache::tags($this->tagPerson($person->id))
            ->put($this->keyById($person->id), $person, self::TTL_SECONDS);

        Cache::tags($this->tagSupabase($supabaseUserId))
            ->put($this->keyBySupabase($supabaseUserId), $person, self::TTL_SECONDS);

        Cache::tags($this->tagPersons())->forget($this->keyAll());
    }
}

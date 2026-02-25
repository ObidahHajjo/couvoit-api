<?php

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Person repository (profile).
 *
 * Cache:
 * - persons:all (tag: persons)
 * - persons:{id} (tags: persons, person:{id})
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

    private function keyAll(): string
    {
        return 'persons:all';
    }

    private function keyById(int $id): string
    {
        return "persons:$id";
    }

    public function all(): Collection
    {
        /** @var Collection<int,Person> $people */
        $people = Cache::tags($this->tagPersons())
            ->remember($this->keyAll(), self::TTL_SECONDS, function () {
                return Person::query()
                    ->with(['car', 'user.role'])
                    ->get();
            });

        foreach ($people as $p) {
            Cache::tags($this->tagPerson($p->id))
                ->put($this->keyById($p->id), $p, self::TTL_SECONDS);
        }

        return $people;
    }

    public function findById(int $id): Person
    {
        /** @var Person $person */
        $person = Cache::tags($this->tagPerson($id))
            ->remember($this->keyById($id), self::TTL_SECONDS, function () use ($id) {
                return Person::query()
                    ->with(['car', 'user.role'])
                    ->findOrFail($id);
            });

        return $person;
    }

    public function create(array $data): Person
    {
        $person = Person::query()
            ->create($data)
            ->loadMissing(['car', 'user.role']);

        Cache::tags($this->tagPerson((int) $person->id))
            ->put($this->keyById((int) $person->id), $person, self::TTL_SECONDS);

        Cache::tags($this->tagPersons())->forget($this->keyAll());

        return $person;
    }

    public function update(int $id, array $data): void
    {
        $person = $this->findById($id);

        $person->update($data);
        $person->refresh()->loadMissing(['car', 'user.role']);

        Cache::tags($this->tagPerson($id))
            ->put($this->keyById($id), $person, self::TTL_SECONDS);

        Cache::tags($this->tagPersons())->forget($this->keyAll());
    }

    public function delete(int $id): void
    {
        $person = Person::query()->findOrFail($id);
        $person->delete();

        Cache::tags($this->tagPerson($id))->flush();
        Cache::tags($this->tagPersons())->forget($this->keyAll());
    }

    public function attachCar(Person $person, int $carId): bool
    {
        $person->car_id = $carId;
        $ok = $person->save();

        $person->refresh()->loadMissing(['car', 'user.role']);

        Cache::tags($this->tagPerson($person->id))
            ->put($this->keyById($person->id), $person, self::TTL_SECONDS);

        Cache::tags($this->tagPersons())->forget($this->keyAll());

        return $ok;
    }
}

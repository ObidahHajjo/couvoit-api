<?php

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;

/**
 * Person repository (profile).
 *
 * Cache:
 * - persons:all (tag: persons)
 * - persons:{id} (tags: persons, person:{id})
 */
readonly class PersonEloquentRepository implements PersonRepositoryInterface
{
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /** @inheritDoc */
    public function all(): Collection
    {
        $people = $this->cache->rememberPersonsAll(function () {
            return Person::query()
                ->with(['car.model.brand', 'car.model.type', 'car.color', 'user.role'])
                ->get();
        });

        foreach ($people as $person) {
            $this->cache->putPerson($person);
        }

        return $people;
    }

    /** @inheritDoc */
    public function findById(int $id): Person
    {
        return $this->cache->rememberPersonById($id, function () use ($id) {
            return Person::query()
                ->with(['car.model.brand', 'car.model.type', 'car.color', 'user.role'])
                ->findOrFail($id);
        });
    }

    /** @inheritDoc */
    public function create(array $data): Person
    {
        $person = Person::query()
            ->create($data)
            ->load(['car.model.brand', 'car.model.type', 'car.color', 'user.role']);

        $this->cache->putPerson($person);
        $this->cache->forgetPersonsAll();

        return $person;
    }

    /** @inheritDoc */
    public function update(int $id, array $data): void
    {
        $person = Person::query()->findOrFail($id);
        $oldCarId = $person->car_id ? (int) $person->car_id : null;

        $person->update($data);
        $person->refresh()->load(['car.model.brand', 'car.model.type', 'car.color', 'user.role']);

        $this->cache->putPerson($person);
        $this->cache->forgetPersonsAll();

        if ($oldCarId !== null) {
            $this->cache->invalidatePersonsByCarId($oldCarId);
        }

        if ($person->car_id !== null) {
            $this->cache->invalidatePersonsByCarId((int) $person->car_id);
        }
    }

    /** @inheritDoc */
    public function delete(int $id): void
    {
        $person = Person::query()->findOrFail($id);
        $carId = $person->car_id ? (int) $person->car_id : null;

        $person->delete();

        $this->cache->forgetPerson($id);
        $this->cache->forgetPersonsAll();

        if ($carId !== null) {
            $this->cache->invalidatePersonsByCarId($carId);
        }
    }

    /** @inheritDoc */
    public function attachCar(Person $person, int $carId): bool
    {
        $oldCarId = $person->car_id ? (int) $person->car_id : null;

        $person->car_id = $carId;
        $ok = $person->save();

        $person->refresh()->load(['car.model.brand', 'car.model.type', 'car.color', 'user.role']);

        $this->cache->putPerson($person);
        $this->cache->forgetPersonsAll();

        if ($oldCarId !== null) {
            $this->cache->invalidatePersonsByCarId($oldCarId);
        }

        $this->cache->invalidatePersonsByCarId($carId);

        return $ok;
    }

    /** @inheritDoc */
    public function restore(int $personId): void
    {
        /** @var Person $person */
        $person = Person::withTrashed()
            ->find($personId);

        if ($person !== null && $person->trashed() && $person->purged_at === null) {
            $person->restore();
        }
    }
}

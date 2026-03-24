<?php

/**
 * @author    [Developer Name]
 *
 * @description Eloquent implementation of PersonRepositoryInterface for managing Person entities.
 */

namespace App\Repositories\Eloquent;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Person repository (profile).
 *
 * Cache:
 * - persons:all (tag: persons)
 * - persons:{id} (tags: persons, person:{id})
 *
 * @implements PersonRepositoryInterface
 */
readonly class PersonEloquentRepository implements PersonRepositoryInterface
{
    /**
     * Create a new person repository instance.
     *
     * @param  RepositoryCacheManager  $cache  The cache manager for caching person data.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Retrieve all persons with their related data.
     *
     * @return Collection<int, Person> Collection of all Person entities with relations.
     */
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

    /**
     * Find a person by their ID.
     *
     * @param  int  $id  The ID of the person to retrieve.
     * @return Person The Person entity with loaded relations.
     *
     * @throws ModelNotFoundException If person is not found.
     */
    public function findById(int $id): Person
    {
        return $this->cache->rememberPersonById($id, function () use ($id) {
            return Person::query()
                ->with(['car.model.brand', 'car.model.type', 'car.color', 'user.role'])
                ->findOrFail($id);
        });
    }

    /**
     * Create a new person record.
     *
     * @param  array  $data  The data to create the person with.
     * @return Person The newly created Person entity.
     */
    public function create(array $data): Person
    {
        $person = Person::query()
            ->create($data)
            ->load(['car.model.brand', 'car.model.type', 'car.color', 'user.role']);

        $this->cache->putPerson($person);
        $this->cache->forgetPersonsAll();

        return $person;
    }

    /**
     * Update an existing person record.
     *
     * @param  int  $id  The ID of the person to update.
     * @param  array  $data  The data to update the person with.
     *
     * @throws ModelNotFoundException If person is not found.
     */
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

    /**
     * Delete a person record.
     *
     * @param  int  $id  The ID of the person to delete.
     *
     * @throws ModelNotFoundException If person is not found.
     */
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

    /**
     * Attach a car to a person.
     *
     * @param  Person  $person  The person to attach the car to.
     * @param  int  $carId  The ID of the car to attach.
     * @return bool True if the operation was successful, false otherwise.
     */
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

    /**
     * Restore a soft-deleted person.
     *
     * @param  int  $personId  The ID of the person to restore.
     */
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

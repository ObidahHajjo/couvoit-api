<?php

namespace App\Services\Implementations;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\PersonServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\Cache\RepositoryCacheManager;

/**
 * Default implementation of person application workflows.
 *
 * @author Application Service
 *
 * @description Handles person-related business operations including CRUD, role management, and trip associations.
 */
readonly class PersonService implements PersonServiceInterface
{
    /**
     * Create a new person service instance.
     *
     * @param  PersonRepositoryInterface  $persons  The person repository
     * @param  TripRepositoryInterface  $trips  The trip repository
     * @param  UserRepositoryInterface  $users  The user repository
     */
    public function __construct(
        private PersonRepositoryInterface $persons,
        private TripRepositoryInterface $trips,
        private UserRepositoryInterface $users,
        private RepositoryCacheManager $cacheManager
    ) {}

    /**
     * List all persons.
     *
     * @return Collection<int, Person> Collection of all persons
     */
    public function list(): Collection
    {
        return $this->persons->all();
    }

    /**
     * Show a person entity.
     *
     * @param  Person  $person  The person to show
     * @return Person The same person entity
     */
    public function show(Person $person): Person
    {
        return $person;
    }

    /**
     * Retrieve a person by identifier.
     *
     * @param  int  $id  The person ID
     * @return Person The found person
     *
     * @throws ModelNotFoundException When person not found
     */
    public function findById(int $id): Person
    {
        return $this->persons->findById($id);
    }

    /**
     * List trips where the person is the driver.
     *
     * @param  Person  $person  The person
     * @return Collection<int, Trip> Collection of trips as driver
     */
    public function tripsAsDriver(Person $person): Collection
    {
        return $this->trips->listByDriver($person->id);
    }

    /**
     * List trips where the person is a passenger.
     *
     * @param  Person  $person  The person
     * @return Collection<int, Trip> Collection of trips as passenger
     */
    public function tripsAsPassenger(Person $person): Collection
    {
        return $this->trips->listByPassenger($person->id);
    }

    /**
     * Update a person's information.
     *
     * @param  Person  $person  The person to update
     * @param  array  $data  The data to update
     * @return Person The updated person
     *
     * @throws ValidationLogicException When data is empty
     */
    public function update(Person $person, array $data): Person
    {
        if (empty($data)) {
            throw new ValidationLogicException('Nothing to update');
        }

        // Profile-only updates now. No 'status' here.
        $this->persons->update($person->id, $data);

        return $this->persons->findById($person->id);
    }

    /**
     * Soft delete a person and associated user.
     *
     * @param  Person  $person  The person to delete
     */
    public function softDelete(Person $person): void
    {
        DB::transaction(function () use ($person): void {
            $user = $person->user;
            $this->users->softDelete($user->id);
            $this->persons->delete($person->id);
            $this->cacheManager->invalidatePersonListAndItem($person->id);
            $this->cacheManager->forgetPerson($person->id);
        });
    }

    /**
     * Create a person and associate with a user.
     *
     * @param  User  $user  The user to associate with
     * @param  array  $data  The person data
     * @return Person The created person
     */
    public function createForUser(User $user, array $data): Person
    {
        $person = $this->persons->create($data);

        $user->person_id = $person->id;
        $user->save();

        return $person;
    }

    /**
     * Update a user's role based on their person ID.
     *
     * @param  int  $personId  The person ID
     * @param  int  $roleId  The new role ID
     * @return Person The updated person
     *
     * @throws ModelNotFoundException When person or user not found
     */
    public function updateUserRoleByPersonId(int $personId, int $roleId): Person
    {
        $user = $this->users->findByPersonId($personId);

        $this->users->update($user, ['role_id' => $roleId]);

        return $this->persons->findById($personId);
    }
}

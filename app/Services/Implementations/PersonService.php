<?php

namespace App\Services\Implementations;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\User;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\PersonServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class PersonService implements PersonServiceInterface
{
    public function __construct(
        private PersonRepositoryInterface $persons,
        private TripRepositoryInterface $trips,
        private UserRepositoryInterface $users,
    ) {}

    /** @inheritDoc */
    public function list(): Collection
    {
        return $this->persons->all();
    }

    /** @inheritDoc */
    public function show(Person $person): Person
    {
        return $person;
    }

    /** @inheritDoc */
    public function findById(int $id): Person
    {
        return $this->persons->findById($id);
    }

    /** @inheritDoc */
    public function tripsAsDriver(Person $person): Collection
    {
        return $this->trips->listByDriver($person->id);
    }

    /** @inheritDoc */
    public function tripsAsPassenger(Person $person): Collection
    {
        return $this->trips->listByPassenger($person->id);
    }

    /** @inheritDoc */
    public function update(Person $person, array $data): Person
    {
        if (empty($data)) {
            throw new ValidationLogicException("Nothing to update");
        }

        // Profile-only updates now. No 'status' here.
        $this->persons->update($person->id, $data);

        return $this->persons->findById($person->id);
    }

    /** @inheritDoc */
    public function softDelete(Person $person): void
    {
        DB::transaction(function () use ($person) :void{
            $user = $person->user;
            $this->users->softDelete($user->id);
            Log::info('user', ['user' => $user]);
            $this->persons->delete($person->id);

        });
    }

    /** @inheritDoc */
    public function createForUser(User $user, array $data): Person
    {
        $person = $this->persons->create($data);

        $user->person_id = $person->id;
        $user->save();

        return $person;
    }

    /** @inheritDoc */
    public function updateUserRoleByPersonId(int $personId, int $roleId): Person
    {
        /** @var User|null $user */
        $user = User::query()->where('person_id', $personId)->firstOrFail();

        $user->role_id = $roleId;
        $user->save();

        return $this->persons->findById($personId);
    }
}

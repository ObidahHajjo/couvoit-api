<?php

namespace App\Services\Interfaces;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Contract for person-related application services.
 */
interface PersonServiceInterface
{
    /**
     * Retrieve all persons.
     *
     * @return Collection<int, Person>
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function list(): Collection;

    /**
     * Retrieve a specific person.
     *
     * @param Person $person Person model instance (route model binding).
     *
     * @return Person
     *
     * @throws ModelNotFoundException If the model does not exist.
     * @throws Throwable              Propagates any repository or infrastructure-level exception.
     */
    public function show(Person $person): Person;

    /**
     * Get trips where the person is the driver.
     *
     * @param Person $person
     *
     * @return Collection<int, mixed>
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function tripsAsDriver(Person $person): Collection;

    /**
     * Get trips where the person is a passenger.
     *
     * @param Person $person
     *
     * @return Collection<int, mixed>
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function tripsAsPassenger(Person $person): Collection;

    /**
     * Update a person profile.
     *
     * @param Person $person
     * @param array<string, mixed> $data
     *
     * @return Person
     *
     * @throws ModelNotFoundException   If the model does not exist.
     * @throws ValidationLogicException If business validation rules fail.
     * @throws Throwable                Propagates any repository or infrastructure-level exception.
     */
    public function update(Person $person, array $data): Person;

    /**
     * Soft delete a person.
     *
     * @param Person $person
     *
     * @return void
     *
     * @throws ModelNotFoundException If the model does not exist.
     * @throws Throwable              Propagates any repository or infrastructure-level exception.
     */
    public function softDelete(Person $person): void;

    /**
     * Create profile for a user if you want lazy profile creation.
     *
     * @param User $user
     * @param array $data
     *
     * @return Person
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function createForUser(User $user, array $data): Person;

    /**
     * Admin: update USER role by person_id.
     *
     * @param int $personId
     * @param int $roleId
     *
     * @return Person
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function updateUserRoleByPersonId(int $personId, int $roleId): Person;
}

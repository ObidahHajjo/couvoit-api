<?php

namespace App\Services\Interfaces;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface PersonServiceInterface
{
    /**
     * Retrieve all persons.
     *
     * @return Collection<int,Person>
     */
    public function list(): Collection;

    /**
     * Show a specific person.
     *
     * @param Person $person
     * @return Person
     *
     * @throws ModelNotFoundException if the model does not exists
     */
    public function show(Person $person): Person;

    /**
     * Get trips where the person is the driver.
     *
     * @param Person $person
     * @return Collection
     */
    public function tripsAsDriver(Person $person): Collection;

    /**
     * Get trips where the person is a passenger.
     *
     * @param Person $person
     * @return Collection
     */
    public function tripsAsPassenger(Person $person): Collection;

    /**
     * Update a profile.
     *
     *
     * @param Person      $person
     * @param array       $data
     *
     * @return Person
     *
     * @throws ModelNotFoundException
     * @throws ValidationLogicException
     */
    public function update(Person $person, array $data): Person;

    /**
     * soft delete a person.
     *
     * @param Person $person
     *
     * @return void
     *
     * @throws ModelNotFoundException
     */
    public function softDelete(Person $person): void;

    public function deactivate(Person $person): void;

    public function reactivate(Person $person): void;

    public function updateRole(string $supabaseUserId, int $roleId): Person;

}

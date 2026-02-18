<?php

namespace App\Services\Interfaces;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
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
     * Deactivate a person account.
     *
     * @param Person $person
     *
     * @return void
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function deactivate(Person $person): void;

    /**
     * Reactivate a person account.
     *
     * @param Person $person
     *
     * @return void
     *
     * @throws Throwable Propagates any repository or infrastructure-level exception.
     */
    public function reactivate(Person $person): void;

    /**
     * Update the role of a person identified by Supabase user ID.
     *
     * @param string $supabaseUserId
     * @param int    $roleId
     *
     * @return Person
     *
     * @throws ModelNotFoundException If no person matches the given Supabase user ID.
     * @throws Throwable              Propagates any repository or infrastructure-level exception.
     */
    public function updateRole(string $supabaseUserId, int $roleId): Person;
}

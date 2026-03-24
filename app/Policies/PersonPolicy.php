<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization rules for User resources.
 *
 * Admin users are granted all abilities via {@see before()}.
 * Non-admin users can only interact with their own profile and related views.
 */
class PersonPolicy
{
    /**
     * Grant all abilities to admins.
     *
     * Returning true authorizes, returning null defers to ability methods.
     *
     * @param User $user
     * @return bool|null
     */
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can list persons.
     *
     * @param User $user
     * @return Response
     */
    public function viewAny(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seuls les administrateurs peuvent consulter la liste des utilisateurs.");
    }

    /**
     * Determine whether the user can view the given person.
     *
     * @param User $user
     * @param Person $person
     * @return Response
     */
    public function view(User $user, Person $person): Response
    {
        return $user->isAdmin() || $user->person_id === $person->id
            ? Response::allow()
            : Response::deny('Vous ne pouvez consulter que votre propre profil.');
    }

    /**
     * Determine whether the user can view trips where they are the driver.
     *
     * @param User $user
     * @param Person $person
     * @return Response
     */
    public function viewTripsDriver(User $user, Person $person): Response
    {
        return $user->isAdmin() || $user->person_id === $person->id
            ? Response::allow()
            : Response::deny('Vous ne pouvez consulter que vos propres trajets en tant que conducteur.');
    }

    /**
     * Determine whether the user can view trips where they are a passenger.
     *
     * @param User $user
     * @param Person $person
     * @return Response
     */
    public function viewTripsPassenger(User $user, Person $person): Response
    {
        return $user->isAdmin() || $user->person_id === $person->id
            ? Response::allow()
            : Response::deny('Vous ne pouvez consulter que vos propres trajets en tant que passager.');
    }

    /**
     * Determine whether the user can create a person profile.
     *
     * This policy assumes the authenticated user has already been resolved.
     *
     * @param User $user
     * @return Response
     */
    public function create(User $user): Response
    {
        return $user->is_active
            ? Response::allow()
            : Response::deny('Seuls les utilisateurs actifs peuvent créer un profil.');
    }

    /**
     * Determine whether the user can update the given person.
     *
     * @param User $user
     * @param Person $person
     * @return Response
     */
    public function update(User $user, Person $person): Response
    {
        return $user->isAdmin() || $user->person_id === $person->id
            ? Response::allow()
            : Response::deny('Vous ne pouvez modifier que votre propre profil.');
    }

    /**
     * Determine whether the user can delete the given person.
     *
     * Non-admin users are denied; admins are granted via {@see before()}.
     *
     * @param User $user
     * @param Person $person
     * @return Response
     */
    public function delete(User $user, Person $person): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : $user->person_id === $person->id
                ? Response::allow()
                : Response::deny('Suppression interdite : réservée à un administrateur.');
    }

    /**
     * Determine whether the user can update roles.
     *
     * @param User $user
     * @return Response
     */
    public function updateRole(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent mettre à jour les rôles.');
    }
}

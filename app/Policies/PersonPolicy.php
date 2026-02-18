<?php

namespace App\Policies;

use App\Models\Person;
use Illuminate\Auth\Access\Response;

/**
 * Authorization rules for Person resources.
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
     * @param Person $user
     * @return bool|null
     */
    public function before(Person $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can list persons.
     *
     * @param Person $user
     * @return Response
     */
    public function viewAny(Person $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seuls les administrateurs peuvent consulter la liste des utilisateurs.");
    }

    /**
     * Determine whether the user can view the given person.
     *
     * @param Person $user
     * @param Person $person
     * @return Response
     */
    public function view(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que votre propre profil.");
    }

    /**
     * Determine whether the user can view trips where they are the driver.
     *
     * @param Person $user
     * @param Person $person
     * @return Response
     */
    public function viewTripsDriver(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que vos propres trajets en tant que conducteur.");
    }

    /**
     * Determine whether the user can view trips where they are a passenger.
     *
     * @param Person $user
     * @param Person $person
     * @return Response
     */
    public function viewTripsPassenger(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que vos propres trajets en tant que passager.");
    }

    /**
     * Determine whether the user can create a person profile.
     *
     * This policy assumes the authenticated user has already been resolved.
     *
     * @param Person $user
     * @return Response
     */
    public function create(Person $user): Response
    {
        return $user->exists
            ? Response::allow()
            : Response::deny("Profil utilisateur introuvable.");
    }

    /**
     * Determine whether the user can update the given person.
     *
     * @param Person $user
     * @param Person $person
     * @return Response
     */
    public function update(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez modifier que votre propre profil.");
    }

    /**
     * Determine whether the user can delete the given person.
     *
     * Non-admin users are denied; admins are granted via {@see before()}.
     *
     * @param Person $user
     * @param Person $person
     * @return Response
     */
    public function delete(Person $user, Person $person): Response
    {
        return Response::deny("Suppression interdite : réservée à un administrateur.");
    }

    /**
     * Determine whether the user can update roles.
     *
     * @param Person $user
     * @return Response
     */
    public function updateRole(Person $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seuls les administrateurs peuvent mettre à jour les roles");
    }
}

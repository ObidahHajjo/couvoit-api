<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authorization rules for Car resources.
 *
 * Admin users are granted all abilities via {@see before()}.
 * Non-admin users:
 * - must be active
 * - can only view/update/delete their own car
 * - can only create a car if they don't already have one
 */
class CarPolicy
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
     * Determine whether the user can list cars.
     *
     * Controller layer restricts non-admin listing to the user's own car.
     *
     * @param User $user
     * @return Response
     */
    public function viewAny(User $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : accès aux voitures interdit.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view the given car.
     *
     * @param User $user
     * @param Car    $car
     * @return Response
     */
    public function view(User $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny('Compte inactif : accès aux voitures interdit.');
        }

        $person = $user->person;

        if (! $person ||! $user->isDriver()) {
            return Response::deny("Vous n'avez pas de voiture associée à votre compte.");
        }

        if ((int) $person->car_id !== $car->id) {
            return Response::deny('Vous ne pouvez consulter que votre propre voiture.');
        }

        return Response::allow();
    }


    /**
     * Determine whether the user can create a car.
     *
     * @param User $user
     * @return Response
     */
    public function create(User $user): Response
    {
        if (! $user->is_active) {
            return Response::deny('Compte inactif : création de voiture interdite.');
        }

        $person = $user->person;

        if (! $person) {
            return Response::deny("Profil utilisateur introuvable.");
        }

        if ($user->isDriver()) {
            return Response::deny("Vous avez déjà une voiture. Impossible d'en créer une autre.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the given car.
     *
     * @param User $user
     * @param Car    $car
     * @return Response
     */
    public function update(User $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny('Compte inactif : modification de voiture interdite.');
        }

        $person = $user->person;

        if (! $person || !$user->isDriver()) {
            return Response::deny("Vous n'avez pas de voiture à modifier.");
        }

        if ((int) $person->car_id !== $car->id) {
            return Response::deny('Vous ne pouvez modifier que votre propre voiture.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the given car.
     *
     * @param User $user
     * @param Car    $car
     * @return Response
     */
    public function delete(User $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny('Compte inactif : suppression de voiture interdite.');
        }

        $person = $user->person;

        if (! $person || !$user->isDriver()) {
            return Response::deny("Vous n'avez pas de voiture à supprimer.");
        }

        if ((int) $person->car_id !== $car->id) {
            return Response::deny('Vous ne pouvez supprimer que votre propre voiture.');
        }

        return Response::allow();
    }
}

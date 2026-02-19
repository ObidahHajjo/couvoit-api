<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Person;
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
     * @param Person $user
     * @return bool|null
     */
    public function before(Person $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can list cars.
     *
     * Controller layer restricts non-admin listing to the user's own car.
     *
     * @param Person $user
     * @return Response
     */
    public function viewAny(Person $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : accès aux voitures interdit.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view the given car.
     *
     * @param Person $user
     * @param Car    $car
     * @return Response
     */
    public function view(Person $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : accès aux voitures interdit.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Vous n'avez pas de voiture associée à votre compte.");
        }

        if ($user->car_id !== $car->id) {
            return Response::deny("Vous ne pouvez consulter que votre propre voiture.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create a car.
     *
     * @param Person $user
     * @return Response
     */
    public function create(Person $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : création de voiture interdite.");
        }

        if (! is_null($user->car_id)) {
            return Response::deny("Vous avez déjà une voiture. Impossible d'en créer une autre.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the given car.
     *
     * @param Person $user
     * @param Car    $car
     * @return Response
     */
    public function update(Person $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : modification de voiture interdite.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Vous n'avez pas de voiture à modifier.");
        }

        if ($user->car_id !== $car->id) {
            return Response::deny("Vous ne pouvez modifier que votre propre voiture.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the given car.
     *
     * @param Person $user
     * @param Car    $car
     * @return Response
     */
    public function delete(Person $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : suppression de voiture interdite.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Vous n'avez pas de voiture à supprimer.");
        }

        if ($user->car_id !== $car->id) {
            return Response::deny("Vous ne pouvez supprimer que votre propre voiture.");
        }

        return Response::allow();
    }
}

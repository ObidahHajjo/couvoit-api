<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Person;
use Illuminate\Auth\Access\Response;

class CarPolicy
{
    public function before(Person $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(Person $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : accès aux voitures interdit.");
        }

        // Controller restricts non-admin listing to their own car.
        return Response::allow();
    }

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

    public function update(Person $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : modification de voiture interdite.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Vous n'avez pas de voiture à modifier.");
        }

        if ( $user->car_id !== $car->id) {
            return Response::deny("Vous ne pouvez modifier que votre propre voiture.");
        }

        return Response::allow();
    }

    public function delete(Person $user, Car $car): Response
    {
        if (! $user->is_active) {
            return Response::deny("Compte inactif : suppression de voiture interdite.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Vous n'avez pas de voiture à supprimer.");
        }

        if ( $user->car_id !==  $car->id) {
            return Response::deny("Vous ne pouvez supprimer que votre propre voiture.");
        }

        return Response::allow();
    }
}

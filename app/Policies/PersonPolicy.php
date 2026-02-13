<?php

namespace App\Policies;

use App\Models\Person;
use Illuminate\Auth\Access\Response;

class PersonPolicy
{
    public function before(Person $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(Person $user): Response
    {
        if (!$user->isAdmin()) {
            return Response::deny("Seuls les administrateurs peuvent consulter la liste des utilisateurs.");
        }

        return Response::allow();
    }

    public function view(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que votre propre profil.");
    }

    public function viewTripsDriver(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que vos propres trajets en tant que conducteur.");
    }

    public function viewTripsPassenger(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez consulter que vos propres trajets en tant que passager.");
    }

    public function create(Person $user): Response
    {
        if (!$user->exists) {
            return Response::deny("Profil utilisateur introuvable.");
        }

        return Response::allow();
    }

    public function update(Person $user, Person $person): Response
    {
        return $user->id === $person->id
            ? Response::allow()
            : Response::deny("Vous ne pouvez modifier que votre propre profil.");
    }

    public function delete(Person $user, Person $person): Response
    {
        return Response::deny("Suppression interdite : réservée à un administrateur.");
    }
}

<?php

namespace App\Policies;

use App\Models\Person;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class PersonPolicy
{
    public function before(Person $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(Person $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("Seuls les administrateurs peuvent consulter la liste des utilisateurs.");
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
        return $user->exists
            ? Response::allow()
            : Response::deny("Profil utilisateur introuvable.");
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

    public function updateRole(Person $user): Response
    {
        return ($user->isAdmin())
            ? Response::allow()
            : Response::deny("Seuls les administrateurs peuvent mettre à jour les roles");
    }
}

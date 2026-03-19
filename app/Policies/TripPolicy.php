<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\Models\Trip;
use Illuminate\Auth\Access\Response;

/**
 * Authorization policy for Trip actions.
 *
 * Admin users are granted all abilities via {@see before()}.
 * Non-admin users:
 * - must be active to view/list/create/update/cancel/reserve
 * - can create trips only for themselves (and must have a car)
 * - can update/cancel only their own trips
 * - can reserve only for themselves and not on their own trip
 * - cannot delete trips (admins only)
 */
class TripPolicy
{
    /**
     * Grant all abilities to admins.
     *
     * @param User $user
     * @return bool|null
     */
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can list trips.
     *
     * @param User $user
     * @return Response
     */
    public function viewAny(User $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Inactive users cannot view the list of trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view a specific trip.
     *
     * @param User $user
     * @param Trip   $trip
     * @return Response
     */
    public function view(User $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny('Inactive users cannot view trips.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view the passengers of a trip.
     *
     * @param User $user
     * @param Trip   $trip
     * @return Response
     */
    public function viewPassengers(User $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny('Inactive users cannot view passengers.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        $isDriver = $trip->person_id === $user->person_id;
        $isPassenger = $trip->passengers
            ->contains(fn ($passenger) => (int) $passenger->id === $user->person_id);

        return ($isDriver || $isPassenger)
            ? Response::allow()
            : Response::deny('Vous ne pouvez consulter que vos trajets ou vos réservations.');
    }

    /**
     * Determine whether the user can create a trip for themselves.
     *
     * Conditions:
     * - user must be active
     * - user must have a car
     *
     * @param User $user
     * @return Response
     */
    public function create(User $user): Response
    {
        if (! $user->is_active) {
            return Response::deny('Only active users can create trips.');
        }

        if (! $user->canPublishTrip()) {
            return Response::deny('Seuls les conducteurs peuvent publier un trajet.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update a trip.
     *
     * Only the driver of the trip (or admin via before) may update it.
     *
     * @param User $user
     * @param Trip   $trip
     * @return Response
     */
    public function update(User $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny('Only active users can update trips.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        return $trip->person_id === $user->person_id
            ? Response::allow()
            : Response::deny('Only the driver of this trip can update it.');
    }

    /**
     * Determine whether the user can delete trips.
     *
     * Admins are granted via before(); non-admins are denied.
     *
     * @param User $user
     * @return Response
     */
    public function delete(User $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny('Only active users can delete trips.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        return (int) $trip->person_id === (int) $user->person_id
            ? Response::allow()
            : Response::deny('Vous ne pouvez supprimer que vos propres trajets.');
    }


    /**
     * Determine whether the user can cancel a trip.
     *
     * Only the driver of the trip (or admin via before) may cancel it,
     * and only if the trip has not started.
     *
     * @param User $user
     * @param Trip   $trip
     * @return Response
     */
    public function cancel(User $user, Trip $trip): Response
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservations are closed.');
        }

        if (! $user->is_active) {
            return Response::deny('Only active users can cancel trips.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        return (int) $trip->person_id === (int) $user->person_id
            ? Response::allow()
            : Response::deny('Only the driver of this trip can cancel it.');
    }

    /**
     * Determine whether the user can cancel a reservation on a trip.
     *
     * Trip must not have started. The "which passenger" decision is handled
     * by controller/service (admin may cancel any, passenger only own).
     *
     * @param User $user
     * @param Trip   $trip
     * @return Response
     */
    public function cancelReservation(User $user, Trip $trip): Response
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservation cannot be canceled.');
        }

        if (! $user->is_active) {
            return Response::deny('Only active users can cancel reservations.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        if (! $user->canBookTrip()) {
            return Response::deny('Vous ne pouvez pas annuler de réservation.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can reserve a seat on a trip.
     *
     * Rules:
     * - trip must not have started
     * - user must be active
     * - non-admin users can only reserve for themselves
     * - driver cannot reserve their own trip
     *
     * @param User $user
     * @param Trip   $trip
     * @param Person $passenger
     * @return Response
     */
    public function reserve(User $user, Trip $trip, ?Person $passenger = null): Response
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservations are closed.');
        }

        if (! $user->is_active) {
            return Response::deny('Only active users can reserve seats.');
        }

        if ($user->isAdmin()) {
            return Response::deny('Un administrateur ne peut pas réserver de place.');
        }

        if (! $user->canBookTrip()) {
            return Response::deny('Vous ne pouvez pas réserver de place.');
        }

        if ($trip->person_id === $user->person_id) {
            return Response::deny('The driver cannot reserve a seat on their own trip.');
        }

        if ($passenger !== null && $passenger->id !== $user->person_id) {
            return Response::deny('Vous ne pouvez réserver que pour vous-même.');
        }

        return Response::allow();
    }
}

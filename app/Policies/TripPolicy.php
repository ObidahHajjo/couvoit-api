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
     * Admin bypass.
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
            return Response::deny("Inactive users cannot view trips.");
        }

        $person = $user->person;

        if (! $person) {
            return Response::deny("User has no profile.");
        }

        // Driver can see passengers
        if ($trip->person_id === $person->id) return Response::allow();

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
            return Response::deny("Inactive users cannot view passengers.");
        }

        $person = $user->person;

        if (! $person) {
            return Response::deny("User has no profile.");
        }

        // Driver can see passengers
        if ($trip->person_id === $person->id) return Response::allow();

        // Passenger of this trip can see passengers
        $isPassenger = $trip->passengers()
            ->where('person_id', $person->id)
            ->exists();

        if ($isPassenger) {
            return Response::allow();
        }

        // Optional: admin override
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny("You are not allowed to view this passenger list.");
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
            return Response::deny("Only active users can create trips.");
        }

        $person = $user->person;
        if (is_null($person->car_id)) {
            return Response::deny("Only drivers (users with a car) can create trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create a trip for a specific driver.
     *
     * Non-admin users may only create trips for themselves.
     *
     * @param User $user
     * @param Person $driver
     * @return Response
     */
    public function createFor(User $user, Person $driver): Response
    {
        if (! $user->is_active) {
            return Response::deny("Only active users can create trips.");
        }

        if ($driver->id !== $user->person_id) {
            return Response::deny("You cannot create a trip for another user.");
        }

        $person = $user->person;
        if (is_null($person->car_id)) {
            return Response::deny("Only drivers (users with a car) can create trips.");
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
            return Response::deny("Only active users can update trips.");
        }

        if ($trip->person_id !== $user->person_id) {
            return Response::deny("Only the driver of this trip can update it.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete trips.
     *
     * Admins are granted via before(); non-admins are denied.
     *
     * @param User $user
     * @return Response
     */
    public function delete(User $user): Response
    {
        if(!$user->isAdmin()) return Response::deny('You are not allowed to delete trips.');
        return Response::allow();
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
            return Response::deny("Only active users can cancel trips.");
        }

        if ($trip->person_id !== $user->person_id) {
            return Response::deny("Only the driver of this trip can cancel it.");
        }

        return Response::allow();
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
            return Response::deny("Only active users can cancel reservations.");
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
    public function reserve(User $user, Trip $trip, Person $passenger): Response
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservations are closed.');
        }

        if (! $user->is_active) {
            return Response::deny("Only active users can reserve seats.");
        }

        if ($passenger->id !== $user->person_id) {
            return Response::deny("You can only reserve a seat for yourself.");
        }

        if ($trip->person_id === $passenger->id) {
            return Response::deny("The driver cannot reserve a seat on their own trip.");
        }

        return Response::allow();
    }
}

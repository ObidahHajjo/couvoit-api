<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Trip;
use Illuminate\Auth\Access\Response;

/**
 * Class TripPolicy
 *
 * Authorization policy for Trip actions.
 *
 * Handles access control for viewing, creating, updating,
 * deleting and reserving trips.
 */
class TripPolicy
{
    /**
     * Admin bypass.
     *
     * If the authenticated user is an admin,
     * all abilities are automatically allowed.
     *
     * @param Person $user
     * @param string $ability
     * @return bool|null
     */
    public function before(Person $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can list trips.
     *
     * @param Person $user
     * @return Response
     */
    public function viewAny(Person $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Inactive users cannot view the list of trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view a specific trip.
     *
     * @param Person $user
     * @param Trip $trip
     * @return Response
     */
    public function view(Person $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny("Inactive users cannot view trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view the passengers of a trip.
     *
     * Currently, any authenticated active user can view passengers.
     * You may restrict this later to driver or passengers only.
     *
     * @param Person $user
     * @param Trip $trip
     * @return Response
     */
    public function viewPassengers(Person $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny("Inactive users cannot view passengers.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create a trip for themselves.
     *
     * Conditions:
     * - User must be active
     * - User must have a car
     *
     * @param Person $user
     * @return Response
     */
    public function create(Person $user): Response
    {
        if (! $user->is_active) {
            return Response::deny("Only active users can create trips.");
        }

        if (is_null($user->car_id)) {
            return Response::deny("Only drivers (users with a car) can create trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create a trip
     * for a specific driver (person_id provided).
     *
     * Non-admin users may only create trips for themselves.
     *
     * @param Person $user
     * @param Person $driver
     * @return Response
     */
    public function createFor(Person $user, Person $driver): Response
    {
        if (! $user->is_active) {
            return Response::deny("Only active users can create trips.");
        }

        if ($driver->id !== $user->id) {
            return Response::deny("You cannot create a trip for another user.");
        }

        if (is_null($driver->car_id)) {
            return Response::deny("Only drivers (users with a car) can create trips.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update a trip.
     *
     * Only the driver of the trip (or admin) may update it.
     *
     * @param Person $user
     * @param Trip $trip
     * @return Response
     */
    public function update(Person $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::deny("Only active users can update trips.");
        }

        if ($trip->person_id !== $user->id) {
            return Response::deny("Only the driver of this trip can update it.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete a trip.
     *
     * Only the driver of the trip (or admin) may delete it.
     *
     * @param Person $user
     * @return Response
     */
    public function delete(Person $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny("You cannot delete trips.");
    }

    /**
     * Determine whether the user can cancel a trip.
     *
     * Only the driver of the trip (or admin) may delete it.
     *
     * @param Person $user
     * @param Trip $trip
     * @return Response
     */
    public function cancel(Person $user, Trip $trip): Response
    {
        if ($trip->departure_time <= now()) return Response::deny('Trip already started; reservations are closed.');
        if(! $user->is_active) Response::deny("Only active users can cancel trips.");
        if($trip->person_id !== $user->id) Response::deny("Only the driver of this trip can cancel it.");
        return Response::allow();
    }

    /**
     * Cancel a reservation on a trip.
     * - Passenger can cancel their own reservation if not started.
     * - Admin can cancel any reservation (handled in controller/service).
     *
     * IMPORTANT: Policy only checks access at the trip level.
     * The "which person_id" decision happens in controller:
     *   - if admin => allow request person_id
     *   - else => force auth id
     */
    public function cancelReservation(Person $user, Trip $trip): Response|bool
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservation cannot be canceled.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can reserve a seat on a trip.
     *
     * Rules:
     * - User must be active
     * - Non-admin users can only reserve for themselves
     * - The driver cannot reserve their own trip
     *
     * @param Person $user
     * @param Trip $trip
     * @param Person $passenger
     * @return Response
     */
    public function reserve(Person $user, Trip $trip, Person $passenger): Response
    {
        if ($trip->departure_time <= now()) {
            return Response::deny('Trip already started; reservations are closed.');
        }

        if (! $user->is_active) {
            return Response::deny("Only active users can reserve seats.");
        }

        if ($passenger->id !== $user->id) {
            return Response::deny("You can only reserve a seat for yourself.");
        }

        if ($trip->person_id === $passenger->id) {
            return Response::deny("The driver cannot reserve a seat on their own trip.");
        }

        return Response::allow();
    }
}

<?php

namespace App\Services\Interfaces;

use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Contract for trip-related application services.
 */
interface TripServiceInterface
{
    /**
     * Search trips by optional criteria.
     *
     * @param string|null $startingCity Starting city name (case-insensitive).
     * @param string|null $arrivalCity  Arrival city name (case-insensitive).
     * @param string|null $tripDate     Trip date in format YYYY-MM-DD.
     * @param int         $perPage      Pagination size.
     *
     * @return LengthAwarePaginator
     *
     * @throws Throwable On repository/database errors.
     */
    public function searchTrips(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int $perPage = 15
    ): LengthAwarePaginator;

    /**
     * Get passengers of a given trip.
     *
     * @param Trip $trip
     *
     * @return Collection<int, Person> Collection of passengers.
     *
     * @throws Throwable On repository/database errors.
     */
    public function getTripPassengers(Trip $trip): Collection;

    /**
     * Create a trip for the authenticated user (or for a given driver when allowed).
     *
     * Expected payload keys:
     * - starting_address (array{street_number:string, street_name:string, postal_code:string, city_name:string})
     * - arrival_address  (array{street_number:string, street_name:string, postal_code:string, city_name:string})
     * - trip_datetime (string|\DateTimeInterface)
     * - kms (float|int)
     * - available_seats (int)
     * - smoking_allowed (bool, optional)
     * - person_id (int, optional)
     *
     * @param array<string, mixed> $payload
     * @param Person              $authPerson Authenticated user.
     *
     * @return Trip Created trip (usually reloaded with relations).
     *
     * @throws ForbiddenException If user tries to create for another driver (non-admin) or driver has no car.
     * @throws Throwable          On transaction/database errors.
     */
    public function createTrip(array $payload, Person $authPerson): Trip;

    /**
     * Cancel a trip (soft delete).
     *
     * @param Trip   $trip       Trip instance to cancel.
     * @param Person $authPerson Authenticated user.
     *
     * @return void
     *
     * @throws ForbiddenException If the authenticated user is not allowed to cancel the trip.
     * @throws Throwable          On transaction/database errors.
     */
    public function cancelTrip(Trip $trip, Person $authPerson): void;

    /**
     * Update a trip.
     *
     * Payload keys (optional):
     * - kms
     * - trip_datetime
     * - available_seats
     * - smoking_allowed
     * - starting_address (array)
     * - arrival_address (array)
     *
     * @param Trip                $trip       Trip instance to update.
     * @param array<string, mixed> $payload    Update data (validated).
     * @param Person              $authPerson  Authenticated user.
     *
     * @return Trip Updated trip (usually reloaded with relations).
     *
     * @throws NotFoundException If trip no longer exists.
     * @throws ForbiddenException If the authenticated user is not allowed to update the trip.
     * @throws Throwable         On transaction/database errors.
     */
    public function updateTrip(Trip $trip, array $payload, Person $authPerson): Trip;

    /**
     * Delete a trip and its reservations (force delete).
     *
     * @param Trip   $trip       Trip instance to delete.
     * @param Person $authPerson Authenticated user.
     *
     * @return void
     *
     * @throws NotFoundException  If trip no longer exists.
     * @throws ForbiddenException If the authenticated user is not allowed to delete the trip.
     * @throws Throwable          On transaction/database errors.
     */
    public function deleteTripPermanently(Trip $trip, Person $authPerson): void;

    /**
     * Cancel a reservation for a passenger on a trip.
     *
     * @param Trip   $trip       Trip instance.
     * @param int    $personId   Passenger id.
     * @param Person $authPerson Authenticated user.
     *
     * @return bool True if reservation deleted, false if it did not exist (depends on repository behavior).
     *
     * @throws NotFoundException  If trip no longer exists.
     * @throws ForbiddenException If the authenticated user is not allowed to cancel this reservation.
     * @throws Throwable          On transaction/database errors.
     */
    public function cancelReservation(Trip $trip, int $personId, Person $authPerson): bool;

    /**
     * Reserve a seat for a passenger on a trip.
     *
     * @param Trip   $trip       Trip instance.
     * @param int    $personId   Passenger id.
     * @param Person $authPerson Authenticated user.
     *
     * @return bool True if reservation created, false if already existed (depends on repository behavior).
     *
     * @throws ForbiddenException       If reserving for another user (non-admin).
     * @throws ValidationLogicException If driver tries to reserve own trip.
     * @throws ConflictException        If no seat left.
     * @throws Throwable                On transaction/database errors.
     */
    public function reserveSeat(Trip $trip, int $personId, Person $authPerson): bool;

    /**
     * Fetch a person by id (throws if not found).
     *
     * @param int $personId
     *
     * @return Person
     *
     * @throws ModelNotFoundException If the person does not exist.
     * @throws Throwable                                           On repository/database errors.
     */
    public function getPersonById(int $personId): Person;
}

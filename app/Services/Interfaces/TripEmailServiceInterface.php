<?php

namespace App\Services\Interfaces;

use App\Models\Person;
use App\Models\Trip;

/**
 * Contract for transactional trip email notifications.
 */
interface TripEmailServiceInterface
{
    /**
     * Send reservation confirmation emails to the passenger and driver.
     *
     * @param Trip   $trip      Trip linked to the reservation.
     * @param Person $passenger Passenger whose reservation was created.
     *
     * @return void
     */
    public function sendReservationCreated(Trip $trip, Person $passenger): void;

    /**
     * Send reservation cancellation emails to the passenger and driver.
     *
     * @param Trip   $trip      Trip linked to the cancelled reservation.
     * @param Person $passenger Passenger whose reservation was cancelled.
     *
     * @return void
     */
    public function sendReservationCancelled(Trip $trip, Person $passenger): void;

    /**
     * Notify passengers that a driver cancelled a trip.
     *
     * @param Trip $trip Cancelled trip.
     *
     * @return void
     */
    public function sendTripCancelledByDriver(Trip $trip): void;
}

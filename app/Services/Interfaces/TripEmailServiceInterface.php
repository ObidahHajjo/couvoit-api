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
     */
    public function sendReservationCreated(Trip $trip, Person $passenger): void;

    /**
     * Send reservation cancellation emails to the passenger and driver.
     */
    public function sendReservationCancelled(Trip $trip, Person $passenger): void;

    /**
     * Notify passengers that a driver cancelled a trip.
     */
    public function sendTripCancelledByDriver(Trip $trip): void;
}

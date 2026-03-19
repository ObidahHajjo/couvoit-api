<?php

namespace App\Services\Interfaces;

use App\Models\Person;
use App\Models\Trip;

interface TripEmailServiceInterface
{
    public function sendReservationCreated(Trip $trip, Person $passenger): void;

    public function sendReservationCancelled(Trip $trip, Person $passenger): void;

    public function sendTripCancelledByDriver(Trip $trip): void;
}

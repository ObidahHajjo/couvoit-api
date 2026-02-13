<?php

namespace App\Repositories\Interfaces;

interface ReservationRepositoryInterface
{
    /**
     * Create a reservation for a person on a trip.
     * Returns true if created, false if already exists (unique PK conflict).
     */
    public function create(int $tripId, int $personId): bool;

    /** Count reservations for a trip. */
    public function countByTrip(int $tripId): int;

    /** Delete all reservations for a trip (used for cascade delete). */
    public function deleteByTrip(int $tripId): int;
}

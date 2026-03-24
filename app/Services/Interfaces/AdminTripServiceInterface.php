<?php

namespace App\Services\Interfaces;

use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for admin trip management services.
 */
interface AdminTripServiceInterface
{
    /**
     * List all trips with pagination.
     *
     * @param  int  $perPage  Number of items per page.
     * @return LengthAwarePaginator<int, Trip>
     */
    public function listTrips(int $perPage = 15): LengthAwarePaginator;

    /**
     * Delete a trip.
     *
     * @param  Trip  $trip  Trip to delete.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteTrip(Trip $trip): void;
}

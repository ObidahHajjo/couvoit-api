<?php

namespace App\Services\Interfaces;

use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdminTripServiceInterface
{
    public function listTrips(int $perPage = 15): LengthAwarePaginator;

    public function deleteTrip(Trip $trip): void;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Reservation;
use App\Repositories\Interfaces\ReservationRepositoryInterface;

class ReservationEloquentRepository implements ReservationRepositoryInterface
{
    public function create(int $tripId, int $personId): bool
    {
        Reservation::query()->create([
            'person_id' => $personId,
            'trip_id' => $tripId,
        ]);
        return true;
    }

    public function countByTrip(int $tripId): int
    {
        return Reservation::query()
            ->where('trip_id', $tripId)
            ->count();
    }

    public function deleteByTrip(int $tripId): int
    {
        return Reservation::query()
            ->where('trip_id', $tripId)
            ->delete();
    }
}

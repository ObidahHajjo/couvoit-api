<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Interfaces\AdminTripServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminTripController extends Controller
{
    public function __construct(
        private readonly AdminTripServiceInterface $trips,
    ) {}

    public function index(): JsonResponse
    {
        $trips = $this->trips->listTrips();

        return response()->json($trips);
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->trips->deleteTrip($trip);

        return response()->json(['message' => 'Trip deleted successfully.']);
    }
}

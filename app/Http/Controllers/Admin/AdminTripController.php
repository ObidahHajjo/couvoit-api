<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Interfaces\AdminTripServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Trips', description: 'Trip management operations for administrators.')]
/**
 * Handles trip management operations for administrators.
 */
class AdminTripController extends Controller
{
    /**
     * Create a new admin trip controller instance.
     */
    public function __construct(
        private readonly AdminTripServiceInterface $trips,
    ) {}

    #[OA\Get(
        path: '/admin/trips',
        operationId: 'adminTripList',
        summary: 'List all trips',
        tags: ['Admin - Trips'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all trips.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $page = (int) $request->query('page', 1);

        $trips = $this->trips->listTrips($perPage);

        return response()->json($trips);
    }

    #[OA\Delete(
        path: '/admin/trips/{trip}',
        operationId: 'adminTripDelete',
        summary: 'Delete a trip',
        tags: ['Admin - Trips'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Trip not found'),
        ]
    )]
    /**
     * Delete a trip.
     */
    public function destroy(Trip $trip): JsonResponse
    {
        $this->trips->deleteTrip($trip);

        return response()->json(['message' => 'Trip deleted successfully.']);
    }
}

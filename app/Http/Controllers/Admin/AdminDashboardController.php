<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Dashboard', description: 'Dashboard and statistics operations for administrators.')]
/**
 * Handles dashboard and statistics operations for administrators.
 */
class AdminDashboardController extends Controller
{
    /**
     * Create a new admin dashboard controller instance.
     */
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    #[OA\Get(
        path: '/admin/dashboard/stats',
        operationId: 'adminDashboardStats',
        summary: 'Get dashboard statistics',
        tags: ['Admin - Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * Get dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->dashboard->getStats();

        return response()->json($stats);
    }
}

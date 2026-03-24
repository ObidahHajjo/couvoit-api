<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
    ) {}

    public function stats(): JsonResponse
    {
        $stats = $this->dashboard->getStats();

        return response()->json($stats);
    }
}

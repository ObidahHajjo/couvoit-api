<?php

declare(strict_types=1);

/**
 * @author Covoiturage Team
 *
 * @description Default implementation of dashboard statistics and metrics.
 */

namespace App\Services\Implementations;

use App\Models\Trip;
use App\Models\User;
use App\Services\Interfaces\DashboardServiceInterface;

/**
 * @description Provides dashboard statistics and aggregated metrics for the application.
 */
readonly class DashboardService implements DashboardServiceInterface
{
    /**
     * @return array{total_users: int, total_trips: int}
     */
    public function getStats(): array
    {
        return [
            'total_users' => User::query()->count(),
            'total_trips' => Trip::query()->count(),
        ];
    }
}

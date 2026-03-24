<?php

declare(strict_types=1);

/**
 * @author Covoiturage Team
 *
 * @description Default implementation of dashboard statistics and metrics.
 */

namespace App\Services\Implementations;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\Trip;
use App\Models\User;
use App\Models\Car;
use App\Models\Brand;
use App\Models\CarModel;
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
            'total_cars' => Car::query()->count(),
            'total_brands' => Brand::query()->count(),
            'total_models' => CarModel::query()->count(),
            'waitingSessions' => SupportChatSession::where('status', 'waiting')->count(),
            'activeSessions' => SupportChatSession::where('status', 'active')->count(),
            'unreadMessages' => SupportChatMessage::where('is_from_admin', false)
                ->where('is_read', false)
                ->count(),
        ];
    }
}

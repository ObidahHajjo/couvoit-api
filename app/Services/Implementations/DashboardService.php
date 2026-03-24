<?php

namespace App\Services\Implementations;

use App\Models\Trip;
use App\Models\User;
use App\Services\Interfaces\DashboardServiceInterface;

readonly class DashboardService implements DashboardServiceInterface
{
    public function getStats(): array
    {
        return [
            'total_users' => User::query()->count(),
            'total_trips' => Trip::query()->count(),
        ];
    }
}

<?php

namespace App\Services\Interfaces;

/**
 * Contract for dashboard statistics services.
 */
interface DashboardServiceInterface
{
    /**
     * Retrieve dashboard statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;
}

<?php

/**
 * @author Admin
 * @description Service implementation for managing trip operations in the admin panel.
 */

namespace App\Services\Implementations;

use App\Models\Trip;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Services\Interfaces\AdminTripServiceInterface;
use App\Services\Interfaces\TripEmailServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;

/**
 * Service implementation for admin trip management operations.
 */
readonly class AdminTripService implements AdminTripServiceInterface
{
    public function __construct(
        private TripRepositoryInterface $trips,
        private TripEmailServiceInterface $tripEmails,
    ) {}

    /**
     * List all trips with pagination.
     *
     * @param int $perPage Number of items per page (default: 15)
     * @return LengthAwarePaginator Paginated list of trips
     */
    public function listTrips(int $perPage = 15): LengthAwarePaginator
    {
        return $this->trips->paginateForAdmin($perPage);
    }

    /**
     * Delete a trip and notify relevant parties.
     *
     * @param Trip $trip The trip to delete
     * @throws \Exception When email sending fails
     * @return void
     */
    public function deleteTrip(Trip $trip): void
    {
        $trip->loadMissing([
            'driver.user',
            'departureAddress.city',
            'arrivalAddress.city',
            'passengers.user'
        ]);

        $driverEmail = $trip->driver->user->email ?? null;
        $hasNotStarted = $trip->departure_time > now();

        if ($hasNotStarted) {
            try {
                $this->tripEmails->sendTripCancelledByDriver($trip);
            } catch (\Exception $e) {
                Log::error("Failed to notify passengers of deleted trip: " . $e->getMessage());
            }
        }

        $this->trips->delete($trip->id);

        if ($driverEmail && $hasNotStarted) {
            try {
                Resend::emails()->send([
                    'from' => config('mail.from.name') . ' <' . config('mail.from.address') . '>',
                    'to' => [$driverEmail],
                    'subject' => 'Trip Deleted - Violation of Policy',
                    'html' => '<p>Hello,</p><p>We regret to inform you that a trip you published has been removed by an administrator due to a violation of our terms, or invalid information.</p><p>If you believe this is a mistake, please contact support.</p>'
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send trip violation email: " . $e->getMessage());
            }
        }
    }
}
<?php

namespace App\Services\Interfaces;

use App\Models\Person;
use App\Models\Trip;

/**
 * Contract for support and participant email contact workflows.
 */
interface ContactEmailServiceInterface
{
    /**
     * Send a support email on behalf of the authenticated person.
     */
    public function sendSupportEmail(Person $sender, string $subject, ?string $message, array $attachments = []): void;

    /**
     * Send an email from a passenger to a trip driver.
     */
    public function sendDriverContactEmail(Trip $trip, Person $sender, string $subject, ?string $message, array $attachments = []): void;

    /**
     * Send an email from a driver to a passenger on their trip.
     */
    public function sendPassengerContactEmail(Trip $trip, Person $passenger, Person $sender, string $subject, ?string $message, array $attachments = []): void;
}

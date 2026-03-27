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
     *
     * @param  Person  $sender  Person sending the email.
     * @param  string  $subject  Email subject.
     * @param  string|null  $message  Optional email message body.
     * @param  array<int, array<string, mixed>>  $attachments  Optional file attachments.
     *
     * @throws \Throwable If the operation fails.
     */
    public function sendSupportEmail(Person $sender, string $subject, ?string $message, array $attachments = []): void;

    /**
     * Send an email from a passenger to a trip driver.
     *
     * @param  Trip  $trip  Trip related to the contact.
     * @param  Person  $sender  Person sending the email.
     * @param  string  $subject  Email subject.
     * @param  string|null  $message  Optional email message body.
     * @param  array<int, array<string, mixed>>  $attachments  Optional file attachments.
     *
     * @throws \Throwable If the operation fails.
     */
    public function sendDriverContactEmail(Trip $trip, Person $sender, string $subject, ?string $message, array $attachments = []): void;

    /**
     * Send an email from a driver to a passenger on their trip.
     *
     * @param  Trip  $trip  Trip related to the contact.
     * @param  Person  $passenger  Passenger receiving the email.
     * @param  Person  $sender  Person sending the email.
     * @param  string  $subject  Email subject.
     * @param  string|null  $message  Optional email message body.
     * @param  array<int, array<string, mixed>>  $attachments  Optional file attachments.
     *
     * @throws \Throwable If the operation fails.
     */
    public function sendPassengerContactEmail(Trip $trip, Person $passenger, Person $sender, string $subject, ?string $message, array $attachments = []): void;
}

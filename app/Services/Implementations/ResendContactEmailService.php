<?php

namespace App\Services\Implementations;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Person;
use App\Models\Trip;
use App\Services\Interfaces\ContactEmailServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Resend\Laravel\Facades\Resend;

/**
 * Resend-backed implementation of support and participant contact emails.
 */
final readonly class ResendContactEmailService implements ContactEmailServiceInterface
{
    /** @inheritDoc */
    public function sendSupportEmail(Person $sender, string $subject, ?string $message, array $attachments = []): void
    {
        $recipient = (string) config('services.support.email', config('mail.from.address'));

        $this->sendEmail(
            [$recipient],
            $subject,
            $this->buildSupportBody($sender, $message),
            $attachments,
            $this->senderReplyTo($sender)
        );
    }

    /** @inheritDoc */
    public function sendDriverContactEmail(Trip $trip, Person $sender, string $subject, ?string $message, array $attachments = []): void
    {
        if ((int) $trip->person_id === (int) $sender->id) {
            throw new ForbiddenException('Driver cannot email themselves.');
        }

        $driver = $trip->driver;
        $driverEmail = $driver?->user?->email;

        if (! is_string($driverEmail) || trim($driverEmail) === '') {
            throw new NotFoundException('Driver email not available.');
        }

        $this->sendEmail(
            [$driverEmail],
            $subject,
            $this->buildTripContactBody($sender, $trip, $message, 'passenger', 'driver'),
            $attachments,
            $this->senderReplyTo($sender)
        );
    }

    /** @inheritDoc */
    public function sendPassengerContactEmail(Trip $trip, Person $passenger, Person $sender, string $subject, ?string $message, array $attachments = []): void
    {
        if ((int) $trip->person_id !== (int) $sender->id) {
            throw new ForbiddenException('Only the driver can email a passenger from this trip.');
        }

        $isPassenger = $trip->passengers()->where('persons.id', $passenger->id)->exists();
        if (! $isPassenger) {
            throw new NotFoundException('Passenger not found on this trip.');
        }

        $passengerEmail = $passenger->user?->email;

        if (! is_string($passengerEmail) || trim($passengerEmail) === '') {
            throw new NotFoundException('Passenger email not available.');
        }

        $this->sendEmail(
            [$passengerEmail],
            $subject,
            $this->buildTripContactBody($sender, $trip, $message, 'driver', 'passenger'),
            $attachments,
            $this->senderReplyTo($sender)
        );
    }

    /**
     * Send a raw HTML email through Resend.
     */
    private function sendEmail(array $to, string $subject, string $html, array $attachments = [], ?array $replyTo = null): void
    {
        $payload = [
            'from' => sprintf('%s <%s>', config('mail.from.name'), config('mail.from.address')),
            'to' => $to,
            'subject' => sprintf('%s - %s', trim($subject), (string) config('app.name')),
            'html' => $html,
            'attachments' => $this->mapAttachments($attachments),
        ];

        if ($replyTo !== null) {
            $payload['reply_to'] = [$replyTo['email']];
        }

        Resend::emails()->send($payload);
    }

    /**
     * Build the support email body.
     */
    private function buildSupportBody(Person $sender, ?string $message): string
    {
        return sprintf(
            '<h2>New support request</h2><p><strong>Sender:</strong> %s</p><p><strong>Email:</strong> %s</p><hr><p>%s</p>',
            e($this->personName($sender)),
            e((string) ($sender->user?->email ?? 'Unknown email')),
            nl2br(e(trim((string) $message)))
        );
    }

    /**
     * Build a participant contact email body.
     */
    private function buildTripContactBody(Person $sender, Trip $trip, ?string $message, string $senderRole, string $recipientRole): string
    {
        $route = trim(implode(' -> ', array_filter([
            $trip->departureAddress?->city?->name,
            $trip->arrivalAddress?->city?->name,
        ])));

        return sprintf(
            '<h2>Trip contact email</h2><p><strong>From:</strong> %s (%s)</p><p><strong>Email:</strong> %s</p><p><strong>To:</strong> %s</p><p><strong>Trip:</strong> %s</p><p><strong>Departure:</strong> %s</p><hr><p>%s</p>',
            e($this->personName($sender)),
            e(Str::headline($senderRole)),
            e((string) ($sender->user?->email ?? 'Unknown email')),
            e(Str::headline($recipientRole)),
            e($route !== '' ? $route : 'Unknown route'),
            e($trip->departure_time?->format('d/m/Y H:i') ?? 'Unknown time'),
            nl2br(e(trim((string) $message)))
        );
    }

    /**
     * Normalize file attachments for Resend.
     */
    private function mapAttachments(array $attachments): array
    {
        return array_values(array_filter(array_map(function ($attachment) {
            if (! $attachment instanceof UploadedFile) {
                return null;
            }

            $contents = file_get_contents($attachment->getRealPath());
            if ($contents === false) {
                return null;
            }

            return [
                'filename' => $attachment->getClientOriginalName(),
                'content' => base64_encode($contents),
            ];
        }, $attachments)));
    }

    /**
     * Build a reply-to value from the sender email when available.
     */
    private function senderReplyTo(Person $sender): ?array
    {
        $email = $sender->user?->email;

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return [
            'email' => $email,
            'name' => $this->personName($sender),
        ];
    }

    /**
     * Resolve a display name for the sender.
     */
    private function personName(Person $person): string
    {
        $fullName = trim(implode(' ', array_filter([
            $person->first_name,
            $person->last_name,
        ], static fn (?string $value): bool => is_string($value) && trim($value) !== '')));

        if ($fullName !== '') {
            return $fullName;
        }

        if (is_string($person->pseudo) && trim($person->pseudo) !== '') {
            return trim($person->pseudo);
        }

        return 'Traveler';
    }
}

<?php

namespace App\Services\Implementations;

use App\Models\Person;
use App\Models\Trip;
use App\Services\Interfaces\TripEmailServiceInterface;
use Illuminate\Support\Collection;
use Resend\Laravel\Facades\Resend;

/**
 * Resend-backed implementation of trip email notifications.
 */
final readonly class ResendTripEmailService implements TripEmailServiceInterface
{
    public function sendReservationCreated(Trip $trip, Person $passenger): void
    {
        $driver = $trip->driver;

        $this->sendToPerson(
            $passenger,
            (string) config('services.resend.trip-reservation-passenger-template'),
            'Reservation confirmed - ' . config('app.name'),
            $trip,
            [
                'HEADLINE' => 'Your reservation is confirmed.',
                'INTRO' => sprintf('You reserved a seat on %s.', $this->routeName($trip)),
                'OTHER_PERSON_LABEL' => 'Driver',
                'OTHER_PERSON_NAME' => $this->personName($driver),
            ]
        );

        if ($driver instanceof Person) {
            $this->sendToPerson(
                $driver,
                (string) config('services.resend.trip-reservation-driver-template'),
                'New reservation received - ' . config('app.name'),
                $trip,
                [
                    'HEADLINE' => 'A passenger reserved your trip.',
                    'INTRO' => sprintf('%s just booked a seat on %s.', $this->personName($passenger), $this->routeName($trip)),
                    'OTHER_PERSON_LABEL' => 'Passenger',
                    'OTHER_PERSON_NAME' => $this->personName($passenger),
                ]
            );
        }
    }

    public function sendReservationCancelled(Trip $trip, Person $passenger): void
    {
        $driver = $trip->driver;

        $this->sendToPerson(
            $passenger,
            (string) config('services.resend.trip-reservation-cancel-passenger-template'),
            'Reservation cancelled - ' . config('app.name'),
            $trip,
            [
                'HEADLINE' => 'Your reservation has been cancelled.',
                'INTRO' => sprintf('Your seat on %s is no longer reserved.', $this->routeName($trip)),
                'OTHER_PERSON_LABEL' => 'Driver',
                'OTHER_PERSON_NAME' => $this->personName($driver),
            ]
        );

        if ($driver instanceof Person) {
            $this->sendToPerson(
                $driver,
                (string) config('services.resend.trip-reservation-cancel-driver-template'),
                'Reservation cancelled by passenger - ' . config('app.name'),
                $trip,
                [
                    'HEADLINE' => 'A passenger cancelled a reservation.',
                    'INTRO' => sprintf('%s cancelled their reservation on %s.', $this->personName($passenger), $this->routeName($trip)),
                    'OTHER_PERSON_LABEL' => 'Passenger',
                    'OTHER_PERSON_NAME' => $this->personName($passenger),
                ]
            );
        }
    }

    public function sendTripCancelledByDriver(Trip $trip): void
    {
        $driver = $trip->driver;

        /** @var Collection<int, Person> $passengers */
        $passengers = $trip->passengers instanceof Collection
            ? $trip->passengers
            : collect();

        foreach ($passengers as $passenger) {
            $this->sendToPerson(
                $passenger,
                (string) config('services.resend.trip-cancelled-passenger-template'),
                'Trip cancelled by driver - ' . config('app.name'),
                $trip,
                [
                    'HEADLINE' => 'Your trip has been cancelled.',
                    'INTRO' => sprintf('The driver cancelled %s.', $this->routeName($trip)),
                    'OTHER_PERSON_LABEL' => 'Driver',
                    'OTHER_PERSON_NAME' => $this->personName($driver),
                ]
            );
        }
    }

    private function sendToPerson(
        Person $recipient,
        string $templateId,
        string $subject,
        Trip $trip,
        array $extraVariables = []
    ): void {
        $email = $recipient->user?->email;

        if (!is_string($email) || trim($email) === '' || trim($templateId) === '') {
            return;
        }

        Resend::emails()->send([
            'from' => sprintf(
                '%s <%s>',
                config('mail.from.name'),
                config('mail.from.address')
            ),
            'to' => [$email],
            'subject' => $subject,
            'template' => [
                'id' => $templateId,
                'variables' => array_merge($this->baseVariables($recipient, $trip), $extraVariables),
            ],
        ]);
    }

    private function baseVariables(Person $recipient, Trip $trip): array
    {
        return [
            'APP_NAME' => $this->appName(),
            'CURRENT_YEAR' => date('Y'),
            'RECIPIENT_NAME' => $this->personName($recipient),
            'ROUTE' => $this->routeName($trip),
            'DEPARTURE_TIME' => $this->formatDateTime($trip->departure_time),
            'ARRIVAL_TIME' => $this->formatDateTime($trip->arrival_time),
            'DRIVER_NAME' => $this->personName($trip->driver),
            'SUPPORT_EMAIL' => (string) config('mail.from.address'),
        ];
    }

    private function routeName(Trip $trip): string
    {
        return $this->formatAddress($trip->departureAddress) . ' -> ' . $this->formatAddress($trip->arrivalAddress);
    }

    private function formatAddress(mixed $address): string
    {
        if ($address === null) {
            return 'Unknown location';
        }

        $parts = array_filter([
            trim((string) ($address->street_number ?? '')),
            trim((string) ($address->street ?? '')),
            trim((string) ($address->city?->postal_code ?? '')),
            trim((string) ($address->city?->name ?? '')),
        ], static fn(string $value): bool => $value !== '');

        return $parts === [] ? 'Unknown location' : implode(' ', $parts);
    }

    private function personName(mixed $person): string
    {
        if (!$person instanceof Person) {
            return 'Unknown user';
        }

        $fullName = trim(implode(' ', array_filter([
            $person->first_name,
            $person->last_name,
        ], static fn(?string $value): bool => is_string($value) && trim($value) !== '')));

        if ($fullName !== '') {
            return $fullName;
        }

        if (is_string($person->pseudo) && trim($person->pseudo) !== '') {
            return trim($person->pseudo);
        }

        return 'traveler';
    }

    private function formatDateTime(mixed $value): string
    {
        return $value?->format('d/m/Y H:i') ?? 'Unknown time';
    }

    private function appName(): string
    {
        return (string) config('app.name', 'Covoiturage');
    }
}

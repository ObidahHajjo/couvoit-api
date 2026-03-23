<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Resend\Laravel\Facades\Resend;

/**
 * Class User
 *
 * authenticated user.
 *
 *
 * ===========================
 * Database Columns (profile)
 * ===========================
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property int $role_id
 * @property int $person_id
 * @property bool|null $is_active
 * @property dateTime $deleted_at
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Person $person
 * @property-read Role role
 */
class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    private const ADMIN_ROLE_ID = 2;

    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'role_id',
        'is_active',
        'person_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $guarded = ['id'];
    protected $casts = [
        'is_active' => 'bool',
        'deleted_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    /**
     * Get the profile linked to the user.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the role assigned to the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role_id === self::ADMIN_ROLE_ID;
    }

    /**
     * Determine whether the user can drive trips.
     */
    public function isDriver(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $this->person !== null && $this->person->car_id !== null;
    }

    /**
     * Determine whether the user is a passenger only.
     */
    public function isPassenger(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $this->person !== null && $this->person->car_id === null;
    }

    /**
     * Get the normalized role name for the user.
     */
    public function roleName(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        if ($this->isDriver()) {
            return 'driver';
        }

        return 'passenger';
    }

    /**
     * Determine whether the user can book trips.
     */
    public function canBookTrip(): bool
    {
        return $this->isPassenger() || $this->isDriver();
    }

    /**
     * Determine whether the user can publish trips.
     */
    public function canPublishTrip(): bool
    {
        return $this->isDriver() || $this->isAdmin();
    }

    /**
     * Determine whether the user can manage all users.
     */
    public function canManageAllUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Determine whether the user can manage all trips.
     */
    public function canManageAllTrips(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Determine whether the user can manage all bookings.
     */
    public function canManageAllBookings(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Send the password reset notification email.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = rtrim((string) config('app.frontend_url'), '/')
            . '/reset-password?token=' . urlencode($token)
            . '&email=' . urlencode($this->email);

        Resend::emails()->send([
            'from' => sprintf(
                '%s <%s>',
                config('mail.from.name'),
                config('mail.from.address')
            ),
            'to' => [$this->email],
            'subject' => 'Reset your ' . config('app.name') . ' password',
            'template' => [
                'id' => (string) config('services.resend.reset-password-template'),
                'variables' => [
                    'APP_NAME' => (string) config('app.name'),
                    'USER_EMAIL' => $this->email,
                    'RESET_URL' => $resetUrl,
                    'EXPIRE_MINUTES' => (string) config('auth.passwords.users.expire'),
                    'SUPPORT_EMAIL' => 'support@ohajjo.online',
                    'CURRENT_YEAR' => date('Y'),
                ],
            ],
        ]);
    }
}

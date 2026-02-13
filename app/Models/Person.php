<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Person
 *
 * Represents an application user linked to Supabase Auth.
 *
 * This model is used as the authenticated user in Laravel (auth()->user()).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the person.
 * @property string $email Email address.
 * @property string|null $first_name First name.
 * @property string|null $last_name Last name.
 * @property string|null $pseudo Username / pseudo (unique).
 * @property string|null $phone Phone number.
 * @property bool $is_active Indicates if the account is active.
 * @property string $supabase_user_id Supabase Auth UUID reference.
 * @property int $role_id Foreign key referencing roles.id.
 * @property int|null $car_id Foreign key referencing cars.id.
 * @property string|null $deleted_at Soft delete timestamp.
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Role|null $role User role (admin/user).
 * @property-read Car|null $car Car assigned to the user.
 * @property-read Collection<int, Trip> $trips Trips where the user is the driver.
 * @property-read Collection<int, Trip> $reservations Trips where the user is passenger.
 */
class Person extends Authenticatable
{
    use SoftDeletes, HasFactory;

    /**
     * Explicit table name because Laravel expects "people"
     * but the table is named "persons".
     *
     * @var string
     */
    protected $table = 'persons';

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supabase_user_id', // Supabase auth UUID
        'email',            // Email address
        'first_name',       // First name
        'last_name',        // Last name
        'pseudo',           // Username
        'phone',            // Phone number
        'is_active',        // Is user active
        'role_id',          // Role reference
        'car_id',           // Linked car reference
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: Person belongs to a Role.
     *
     * Example usage:
     *   $person->role
     *
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relationship: Person belongs to a Car.
     *
     * Example usage:
     *   $person->car
     *
     * @return BelongsTo
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Relationship: Person has many trips as a driver.
     *
     * Example usage:
     *   $person->trips
     *
     * @return HasMany
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Relationship: Person belongs to many trips as a passenger
     * through the reservations pivot table.
     *
     * Example usage:
     *   $person->reservations
     *
     * @return BelongsToMany
     */
    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'reservations', 'person_id', 'trip_id');
    }

    /**
     * Check if the person has admin role.
     *
     * @return bool True if role name is "admin".
     */
    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }
}

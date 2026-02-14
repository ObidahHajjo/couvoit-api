<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Trip
 *
 * Represents a trip created by a driver (Person).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the trip.
 * @property string $departure_time Departure date/time.
 * @property float $distance_km Distance in kilometers.
 * @property int $available_seats Available seats for passengers.
 * @property bool $smoking_allowed Whether smoking is allowed.
 * @property int $departure_address_id Foreign key referencing addresses.id.
 * @property int $arrival_address_id Foreign key referencing addresses.id.
 * @property int $person_id Foreign key referencing persons.id (driver).
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Person|null $driver Driver of the trip.
 * @property-read Collection<int, Person> $passengers Passengers who reserved.
 */
class Trip extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Disable timestamps because the "trips" table does not contain
     * created_at / updated_at columns managed by Laravel.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'departure_time',        // Departure datetime
        'distance_km',           // Distance in km
        'available_seats',       // Available seats
        'departure_address_id',  // Departure address
        'arrival_address_id',    // Arrival address
        'smoking_allowed',       // Smoking allowed
        'person_id'              // Driver id
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: Trip belongs to a Person (driver).
     *
     * @return BelongsTo
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Relationship: Trip has many passengers through reservations table.
     *
     * @return BelongsToMany
     */
    public function passengers(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'reservations', 'trip_id', 'person_id');
    }

    public function departureAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'departure_address_id');
    }

    public function arrivalAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'arrival_address_id');
    }
}

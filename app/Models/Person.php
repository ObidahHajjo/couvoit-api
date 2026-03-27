<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Person
 *
 * Profile aggregate (NOT authenticated).
 *
 * Auth identity lives in App\Models\User. Access it via $person->user.
 *
 * ===========================
 * Database Columns (profile)
 * ===========================
 *
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $pseudo
 * @property string|null $phone
 * @property int|null $car_id
 * @property string|null $deleted_at
 * @property string|null $purged_at
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read User|null $user
 * @property-read Car|null $car
 * @property-read Collection<int, Trip> $trips
 * @property-read Collection<int, Trip> $reservations
 */
class Person extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'persons';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'pseudo',
        'phone',
        'car_id',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'deleted_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    /**
     * Person <-> User (auth identity).
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'person_id');
    }

    /**
     * Get the car assigned to the person.
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Get trips driven by the person.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Get trips reserved by the person.
     */
    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'reservations', 'person_id', 'trip_id');
    }
}

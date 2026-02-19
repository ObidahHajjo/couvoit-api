<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class City
 *
 * Represents a city (name + postal code).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the city.
 * @property string $name City name.
 * @property string $postal_code Postal code of the city.
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Collection<int, Address> $addresses
 */
class City extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "cities" table does not contain
     * created_at / updated_at columns.
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
        'name',        // City name
        'postal_code'  // Postal code
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: City has many addresses.
     *
     * Example usage:
     *   $city->addresses
     *
     * @return HasMany
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}

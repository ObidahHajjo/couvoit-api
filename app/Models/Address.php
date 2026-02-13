<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Address
 *
 * Represents an address entity stored in the "addresses" table.
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the address.
 * @property string $street Street name.
 * @property string $street_number Street number (can include letters like "12B").
 * @property int $city_id Foreign key referencing the city.
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read City|null $city The city linked to this address.
 */
class Address extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "addresses" table does not contain
     * created_at / updated_at columns managed automatically by Laravel.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributes that are mass assignable.
     *
     * Allows Address::create([...]) to fill these fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'street',        // Street name
        'street_number', // Street number
        'city_id'        // ID of the related city
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * Protects the primary key from being overwritten.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: Address belongs to a City.
     *
     * Example usage:
     *   $address->city
     *
     * @return BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

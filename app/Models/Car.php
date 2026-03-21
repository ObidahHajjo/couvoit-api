<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Car
 *
 * Represents a car entity stored in the "cars" table.
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the car.
 * @property string $license_plate Car registration / license plate (unique).
 * @property int $seats Number of seats for this specific car.
 * @property int $model_id Foreign key referencing the car model.
 * @property int $color_id Foreign key referencing the car color.
 *
 * ===========================
 * Relationships
 * ===========================
 * @property-read CarModel|null $model The model of the car.
 * @property-read Color|null $color The color of the car.
 */
class Car extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "cars" table does not contain
     * created_at / updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributes that are mass assignable.
     *
     * This allows Car::create([...]) to fill these fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'license_plate', // Unique license plate of the car
        'seats',         // Number of seats for the car
        'model_id',       // ID of the related car model
        'color_id',        // ID of the related car color
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * Protects the primary key from being overwritten.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Relationship: Car belongs to a CarModel.
     *
     * Example usage:
     *   $car->model
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    /**
     * Relationship: Car belongs to a Color.
     *
     * Example usage:
     *   $car->color
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}

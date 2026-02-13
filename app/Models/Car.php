<?php

namespace App\Models;

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
 * @property int $model_id Foreign key referencing the car model.
 * @property int $color_id Foreign key referencing the car color.
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read CarModel|null $model The model of the car.
 * @property-read Color|null $color The color of the car.
 */
class Car extends Model
{
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
        'model_id',       // ID of the related car model
        'color_id'        // ID of the related car color
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
     * Relationship: Car belongs to a CarModel.
     *
     * Example usage:
     *   $car->model
     *
     * @return BelongsTo
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
     *
     * @return BelongsTo
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}

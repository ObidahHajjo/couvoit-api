<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class CarModel
 *
 * Represents a car model (ex: Golf, Clio, Model S).
 *
 * Stored in the "models" table.
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the model.
 * @property string $name Model name.
 * @property int $seats Number of seats.
 * @property int $brand_id Foreign key referencing brands.id.
 * @property int $type_id Foreign key referencing types.id.
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Brand|null $brand The brand of this model.
 */
class CarModel extends Model
{
    use HasFactory;

    /**
     * Explicit table name because Laravel expects "car_models"
     * but the table is named "models".
     *
     * @var string
     */
    protected $table = 'models';

    /**
     * Disable timestamps because the "models" table does not contain
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
        'name',     // Model name
        'seats',    // Number of seats
        'brand_id', // Brand reference
        'type_id',  // Type reference
        'search_key',
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: CarModel belongs to a Brand.
     *
     * Example usage:
     *   $model->brand
     *
     * @return BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relationship: CarModel belongs to a type.
     *
     * Example usage:
     *   $model->type
     *
     * @return BelongsTo
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }
}

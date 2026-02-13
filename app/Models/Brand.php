<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Brand
 *
 * Represents a car brand (ex: Toyota, BMW, Audi).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the brand.
 * @property string $name Brand name (unique).
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Collection<int, CarModel> $models
 */
class Brand extends Model
{
    /**
     * Disable timestamps because the "brands" table does not contain
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
    protected $fillable = ['name'];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Relationship: Brand has many car models.
     *
     * Example usage:
     *   $brand->models
     *
     * @return HasMany
     */
    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }
}

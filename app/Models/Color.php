<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Color
 *
 * Represents a car color.
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the color.
 * @property string $name Color name (ex: red, blue).
 * @property string $hex_code Hex color code (unique).
 */
class Color extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "colors" table does not contain
     * created_at / updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',      // Color name
        "hex_code"   // Hexadecimal code (ex: #FFFFFF)
    ];
}

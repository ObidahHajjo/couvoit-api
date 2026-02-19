<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Type
 *
 * Represents a car type (ex: SUV, Sedan, Coupe).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the type.
 * @property string $type Type name (unique expected).
 */
class Type extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "types" table does not contain
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
        'type' // Type name (SUV, sedan, etc.)
    ];

    /**
     * Attributes that are NOT mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];
}

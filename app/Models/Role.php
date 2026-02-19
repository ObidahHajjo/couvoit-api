<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Role
 *
 * Represents a user role (admin/user).
 *
 * ===========================
 * Database Columns
 * ===========================
 *
 * @property int $id Unique identifier of the role.
 * @property string $name Role name (unique).
 */
class Role extends Model
{
    use HasFactory;

    /**
     * Disable timestamps because the "roles" table does not contain
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
}

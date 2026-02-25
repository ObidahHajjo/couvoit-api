<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class User
 *
 * authenticated user.
 *
 *
 * ===========================
 * Database Columns (profile)
 * ===========================
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property int $role_id
 * @property int person_id
 * @property bool|null $is_active
 *
 * ===========================
 * Relationships
 * ===========================
 *
 * @property-read Person $person
 * @property-read Role $role
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'role_id',
        'is_active',
        'person_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $guarded = ['id'];
    protected $casts = [
        'is_active' => 'bool',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->role_id === 2;
    }
}

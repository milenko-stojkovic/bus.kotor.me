<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Red u tabeli admins — koristi se za Control panel login (guard control).
 * Admin panel i dalje koristi User + AdminMiddleware; ne mešati UX.
 */
class Admin extends Authenticatable
{
    public $timestamps = true;

    protected $table = 'admins';

    protected $fillable = [
        'username',
        'email',
        'password',
        'control_access',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'control_access' => 'boolean',
        ];
    }
}

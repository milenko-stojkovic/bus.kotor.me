<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Red u tabeli `admins`: guard `control` (dolasci) i guard `panel_admin` (glavni admin panel).
 * `admin_access` i `control_access` su međusobno isključivi (v. `saving`).
 * Operativni pregled rezervacija (User + AdminMiddleware) koristi prefiks `/staff`.
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
        'admin_access',
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
            'admin_access' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Admin $admin): void {
            if ($admin->admin_access && $admin->control_access) {
                if ($admin->isDirty('admin_access') && $admin->admin_access) {
                    $admin->control_access = false;
                } else {
                    $admin->admin_access = false;
                }
            }
        });
    }
}

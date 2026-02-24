<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Uloga (Spatie/ACL kompatibilna struktura). Polja: id, name, guard_name, created_at, updated_at.
 * Relacija: belongsToMany(User) preko role_user.
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
}

<?php

namespace App\Models;

use App\Notifications\NoreplyResetPassword;
use App\Notifications\NoreplyVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Autentifikovani korisnik. user_id u reservations/temp_data može biti null (guest rezervacija).
 * V. docs/auth-and-guests.md za pravila guest vs. autentifikovan.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function tempData(): HasMany
    {
        return $this->hasMany(TempData::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'lang',
        'country',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $locale = is_string($this->lang) && $this->lang === 'cg' ? 'cg' : 'en';
        $this->notify((new NoreplyVerifyEmail())->locale($locale));
    }

    public function sendPasswordResetNotification($token): void
    {
        $locale = is_string($this->lang) && $this->lang === 'cg' ? 'cg' : 'en';
        $this->notify((new NoreplyResetPassword($token))->locale($locale));
    }
}

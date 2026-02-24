<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vozilo. Polja: id, user_id (nullable), license_plate, vehicle_type_id, created_at, updated_at.
 *
 * user_id nullable → guest-vozila (npr. prethodne rezervacije bez naloga). withDefault() na user() da ne baca za NULL.
 * Relacije: belongsTo(User) nullable, belongsTo(VehicleType), hasMany(Reservation).
 */
class Vehicle extends Model
{
    protected $fillable = [
        'user_id',
        'license_plate',
        'vehicle_type_id',
    ];

    /** FK ka User (nullable za guest-vozila). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /** FK ka VehicleType. */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'vehicle_id');
    }
}

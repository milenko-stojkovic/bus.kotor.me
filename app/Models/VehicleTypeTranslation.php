<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lokalizovani naziv i opis tipa vozila. Polja: id, vehicle_type_id, locale, name, description, created_at, updated_at.
 * Unique constraint: (vehicle_type_id, locale). Locale za internacionalizaciju (npr. 'en', 'cg').
 * Relacija: belongsTo(VehicleType).
 */
class VehicleTypeTranslation extends Model
{
    protected $table = 'vehicle_type_translations';

    protected $fillable = [
        'vehicle_type_id',
        'locale',
        'name',
        'description',
    ];

    /** FK ka VehicleType. Unique (vehicle_type_id, locale) u bazi. */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}

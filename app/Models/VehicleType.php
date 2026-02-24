<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Tip vozila. Polja: id, price (decimal 10,2), created_at, updated_at (ako postoje u tabeli).
 * Relacije: hasMany(Vehicle), hasMany(VehicleTypeTranslation). Price za cenu.
 */
class VehicleType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'price',
    ];

    /** Price → decimal(10,2). */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(VehicleTypeTranslation::class, 'vehicle_type_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'vehicle_type_id');
    }

    public function tempData(): HasMany
    {
        return $this->hasMany(TempData::class, 'vehicle_type_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_type_id');
    }

    /** Korisnici koji imaju bar jedno vozilo ovog tipa. */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Vehicle::class, 'vehicle_type_id', 'id', 'id', 'user_id');
    }

    /**
     * Lokalizovani naziv za dati locale (za prikaz cene/teksta).
     * Fallback: prvi dostupan prevod ili prazan string.
     */
    public function getTranslatedName(string $locale): string
    {
        $t = $this->translations()->where('locale', $locale)->first();

        return $t?->name ?? $this->translations()->value('name') ?? '';
    }

    /**
     * Lokalizovani opis za dati locale.
     */
    public function getTranslatedDescription(string $locale): ?string
    {
        $t = $this->translations()->where('locale', $locale)->first();

        return $t?->description ?? $this->translations()->value('description');
    }
}

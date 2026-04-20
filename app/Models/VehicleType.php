<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Log;

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
        $t = $this->translationFor($locale);

        return $t?->name ?? $this->fallbackTranslation()?->name ?? '';
    }

    /**
     * Lokalizovani opis za dati locale.
     */
    public function getTranslatedDescription(string $locale): ?string
    {
        $t = $this->translationFor($locale);

        return $t?->description ?? $this->fallbackTranslation()?->description;
    }

    /**
     * User-facing label: "Name (Description) - 12.00 EUR" (description optional).
     * Locale uses vehicle_type_translations; price comes from vehicle_types.price.
     */
    public function formatLabel(string $locale, string $currency = 'EUR'): string
    {
        $translatedName = $this->getTranslatedName($locale);
        $translatedDescription = $this->getTranslatedDescription($locale);

        $name = $translatedName;
        if ($name === '') {
            Log::warning('vehicle_type_translation_missing', [
                'vehicle_type_id' => $this->id,
                'locale' => $locale,
                'has_name_translation' => $translatedName !== '',
                'has_description_translation' => trim((string) ($translatedDescription ?? '')) !== '',
            ]);
            $name = '#'.$this->id;
        }

        $desc = trim((string) ($translatedDescription ?? ''));
        $price = is_numeric((string) $this->price) ? number_format((float) $this->price, 2, '.', '') : null;

        $label = $name;
        if ($desc !== '') {
            $label .= ' ('.$desc.')';
        }
        if ($price !== null) {
            $label .= ' - '.$price.' '.$currency;
        }

        return $label;
    }

    private function translationFor(string $locale): ?VehicleTypeTranslation
    {
        if ($this->relationLoaded('translations')) {
            /** @var \Illuminate\Support\Collection<int, VehicleTypeTranslation> $t */
            $t = $this->getRelation('translations');

            return $t->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    private function fallbackTranslation(): ?VehicleTypeTranslation
    {
        if ($this->relationLoaded('translations')) {
            /** @var \Illuminate\Support\Collection<int, VehicleTypeTranslation> $t */
            $t = $this->getRelation('translations');

            return $t->first();
        }

        return $this->translations()->first();
    }
}

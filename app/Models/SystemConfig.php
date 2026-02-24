<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sistemska konfiguracija (key-value, integer). Npr. available_parking_slots za broj slotova.
 * Admin panel: izmena vrednosti (npr. available_parking_slots) za kapacitet.
 */
class SystemConfig extends Model
{
    protected $table = 'system_config';

    /** Tabela nema created_at. */
    public const CREATED_AT = null;

    protected $fillable = [
        'name',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public static function getValue(string $name): ?int
    {
        $row = static::query()->where('name', $name)->first();

        return $row?->value;
    }

    public static function setValue(string $name, int $value): bool
    {
        return (bool) static::query()->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    /** Broj dostupnih parking mesta po slotu (za nove dane / kapacitet). */
    public static function availableParkingSlots(): int
    {
        return (int) (static::getValue('available_parking_slots') ?? 0);
    }
}

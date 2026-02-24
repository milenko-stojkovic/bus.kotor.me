<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lokalizovani UI tekstovi (group/key/locale). Koristi se za prikaz teksta u aplikaciji po jeziku.
 * Primer: UiTranslation::get('buttons', 'pay_now', 'cg') → 'Plati sada'
 */
class UiTranslation extends Model
{
    protected $table = 'ui_translations';

    protected $fillable = [
        'group',
        'key',
        'locale',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Vraća prevedeni tekst za group, key i locale. Fallback na prvi dostupan locale ako nema za traženi.
     */
    public static function get(string $group, string $key, string $locale): ?string
    {
        $row = static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->where('locale', $locale)
            ->value('text');

        if ($row !== null) {
            return $row;
        }

        return static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->value('text');
    }
}

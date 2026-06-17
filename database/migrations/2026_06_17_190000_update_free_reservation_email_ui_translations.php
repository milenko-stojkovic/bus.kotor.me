<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'emails', 'key' => 'free_reservation_subject', 'locale' => 'en', 'text' => 'Free reservation confirmation'],
            ['group' => 'emails', 'key' => 'free_reservation_subject', 'locale' => 'cg', 'text' => 'Potvrda besplatne rezervacije'],
            ['group' => 'emails', 'key' => 'free_reservation_body', 'locale' => 'en', 'text' => "Dear %1\$s,\n\nYour free parking reservation has been successfully created.\n\nAttached to this email you will find the free parking reservation confirmation.\n\nPlease keep this confirmation for your records.\n\nBest regards,\nMunicipality of Kotor"],
            ['group' => 'emails', 'key' => 'free_reservation_body', 'locale' => 'cg', 'text' => "Poštovani %1\$s,\n\nVaša besplatna rezervacija parkinga je uspješno kreirana.\n\nUz ovu poruku u prilogu se nalazi potvrda besplatne rezervacije parkinga.\n\nMolimo Vas da je sačuvate radi evidencije.\n\nS poštovanjem,\nOpština Kotor"],
        ];

        $now = now();
        foreach ($rows as $row) {
            DB::table('ui_translations')->updateOrInsert(
                ['group' => $row['group'], 'key' => $row['key'], 'locale' => $row['locale']],
                ['text' => $row['text'], 'updated_at' => $now, 'created_at' => $now],
            );
        }

        Cache::forget('ui_translations:group=emails:locale=cg');
        Cache::forget('ui_translations:group=emails:locale=en');
        foreach (['free_reservation_subject', 'free_reservation_body'] as $key) {
            Cache::forget('ui_translations:any:group=emails:key='.$key);
        }
    }

    public function down(): void
    {
        // Previous copy intentionally not restored.
    }
};

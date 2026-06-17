<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'booking', 'key' => 'duplicate_termini_slot', 'locale' => 'en', 'text' => 'A reservation already exists for this license plate on the selected date with the same arrival time or the same departure time.'],
            ['group' => 'booking', 'key' => 'duplicate_termini_slot', 'locale' => 'cg', 'text' => 'Za istu registarsku tablicu već postoji rezervacija za odabrani datum sa istim vremenom dolaska ili istim vremenom odlaska.'],
        ];

        foreach ($rows as $row) {
            DB::table('ui_translations')->updateOrInsert(
                ['group' => $row['group'], 'key' => $row['key'], 'locale' => $row['locale']],
                ['text' => $row['text'], 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('ui_translations')
            ->where('group', 'booking')
            ->where('key', 'duplicate_termini_slot')
            ->delete();
    }
};

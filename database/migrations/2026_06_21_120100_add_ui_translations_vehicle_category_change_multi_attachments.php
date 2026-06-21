<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'panel', 'key' => 'vehicle_category_change_document', 'locale' => 'cg', 'text' => 'Dokumenti (slika ili PDF)'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_document', 'locale' => 'en', 'text' => 'Documents (image or PDF)'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_document_hint', 'locale' => 'cg', 'text' => 'Možete priložiti više dokumenata ili slika, npr. obje strane saobraćajne dozvole. Najmanje jedan dokument je obavezan. Max 10 MB po fajlu, najviše 5 fajlova.'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_document_hint', 'locale' => 'en', 'text' => 'You can attach multiple documents or images, for example both sides of the vehicle registration document. At least one document is required. Max 10 MB per file, up to 5 files.'],
        ];

        foreach ($rows as $r) {
            DB::table('ui_translations')->updateOrInsert(
                ['group' => $r['group'], 'key' => $r['key'], 'locale' => $r['locale']],
                ['text' => $r['text'], 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        // Keys may have existed before with different text; leave in place on rollback.
    }
};

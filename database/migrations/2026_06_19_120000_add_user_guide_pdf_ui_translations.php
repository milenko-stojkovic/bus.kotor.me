<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'landing', 'key' => 'user_guide_pdf', 'locale' => 'cg', 'text' => 'Uputstvo (PDF)'],
            ['group' => 'landing', 'key' => 'user_guide_pdf', 'locale' => 'en', 'text' => 'User guide (PDF)'],
            ['group' => 'panel', 'key' => 'user_guide_pdf', 'locale' => 'cg', 'text' => 'Uputstvo (PDF)'],
            ['group' => 'panel', 'key' => 'user_guide_pdf', 'locale' => 'en', 'text' => 'User guide (PDF)'],
        ];

        foreach ($rows as $row) {
            DB::table('ui_translations')->updateOrInsert(
                ['group' => $row['group'], 'key' => $row['key'], 'locale' => $row['locale']],
                ['text' => $row['text'], 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach (['landing', 'panel'] as $group) {
            foreach (['cg', 'en'] as $locale) {
                Cache::forget("ui_translations:group={$group}:locale={$locale}");
            }
            Cache::forget("ui_translations:any:group={$group}:key=user_guide_pdf");
        }
    }

    public function down(): void
    {
        DB::table('ui_translations')
            ->where('key', 'user_guide_pdf')
            ->whereIn('group', ['landing', 'panel'])
            ->delete();
    }
};

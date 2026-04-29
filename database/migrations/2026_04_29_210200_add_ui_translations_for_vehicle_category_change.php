<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // Panel: category change flow
            ['group' => 'panel', 'key' => 'vehicle_category_change_requires_approval', 'locale' => 'cg', 'text' => 'Za ovu registarsku tablicu već postoji ranije uklonjeno vozilo sa drugom kategorijom. Promjena kategorije mora biti odobrena od strane administratora. Molimo priložite fotografiju ili PDF saobraćajne dozvole ili drugog dokumenta iz kojeg se vidi registarska tablica i kategorija vozila.'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_requires_approval', 'locale' => 'en', 'text' => 'A previously removed vehicle with a different category already exists for this license plate. Changing the category must be approved by an administrator. Please attach a photo or PDF of the registration document or another document showing the license plate and category.'],

            ['group' => 'panel', 'key' => 'vehicle_category_change_sent', 'locale' => 'cg', 'text' => 'Zahtjev je poslat administratoru.'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_sent', 'locale' => 'en', 'text' => 'Your request has been sent to the administrator.'],

            ['group' => 'panel', 'key' => 'vehicle_category_change_already_sent', 'locale' => 'cg', 'text' => 'Zahtjev je već poslat administratoru.'],
            ['group' => 'panel', 'key' => 'vehicle_category_change_already_sent', 'locale' => 'en', 'text' => 'A request has already been sent to the administrator.'],

            ['group' => 'panel', 'key' => 'vehicle_reactivated', 'locale' => 'cg', 'text' => 'Vozilo je reaktivirano.'],
            ['group' => 'panel', 'key' => 'vehicle_reactivated', 'locale' => 'en', 'text' => 'Vehicle reactivated.'],
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
        DB::table('ui_translations')->where('group', 'panel')->whereIn('key', [
            'vehicle_category_change_requires_approval',
            'vehicle_category_change_sent',
            'vehicle_category_change_already_sent',
            'vehicle_reactivated',
        ])->delete();
    }
};


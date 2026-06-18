<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Align with production labels (vehicle_type_id 1–4, locales cg/en).
        $updates = [
            ['vehicle_type_id' => 1, 'locale' => 'en', 'description' => 'Personal vehicle (4+1, 5+1, 6+1 and 7+1 seats)'],
            ['vehicle_type_id' => 1, 'locale' => 'cg', 'description' => 'Putničko vozilo (4+1, 5+1, 6+1 i 7+1 mjesta)'],
            ['vehicle_type_id' => 2, 'locale' => 'en', 'description' => 'Mini bus (8+1 seats)'],
            ['vehicle_type_id' => 2, 'locale' => 'cg', 'description' => 'Mini bus (8+1 mjesta)'],
            ['vehicle_type_id' => 3, 'locale' => 'en', 'description' => 'Medium bus (9–23 seats)'],
            ['vehicle_type_id' => 3, 'locale' => 'cg', 'description' => 'Autobus (9–23 mjesta)'],
            ['vehicle_type_id' => 4, 'locale' => 'en', 'description' => 'Big bus (over 23 seats)'],
            ['vehicle_type_id' => 4, 'locale' => 'cg', 'description' => 'Autobus (više od 23 mjesta)'],
        ];

        foreach ($updates as $row) {
            DB::table('vehicle_type_translations')
                ->where('vehicle_type_id', $row['vehicle_type_id'])
                ->where('locale', $row['locale'])
                ->update(['description' => $row['description']]);
        }
    }

    public function down(): void
    {
        $previous = [
            ['vehicle_type_id' => 1, 'locale' => 'en', 'description' => 'Passenger car (4+1 to 7+1 seats)'],
            ['vehicle_type_id' => 1, 'locale' => 'cg', 'description' => '4+1 do 7+1 sjedišta'],
            ['vehicle_type_id' => 2, 'locale' => 'en', 'description' => '8+1 seats'],
            ['vehicle_type_id' => 2, 'locale' => 'cg', 'description' => '8+1 sjedište'],
            ['vehicle_type_id' => 3, 'locale' => 'en', 'description' => '9–23 seats'],
            ['vehicle_type_id' => 3, 'locale' => 'cg', 'description' => '9–23 sjedišta'],
            ['vehicle_type_id' => 4, 'locale' => 'en', 'description' => 'over 23 seats'],
            ['vehicle_type_id' => 4, 'locale' => 'cg', 'description' => 'preko 23 sjedišta'],
        ];

        foreach ($previous as $row) {
            DB::table('vehicle_type_translations')
                ->where('vehicle_type_id', $row['vehicle_type_id'])
                ->where('locale', $row['locale'])
                ->update(['description' => $row['description']]);
        }
    }
};

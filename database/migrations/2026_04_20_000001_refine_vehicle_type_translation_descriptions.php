<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Refine descriptions to avoid redundant prefixes like "Bus (...)" since UI shows: Name (Description) - Price.
        // We intentionally only touch known baseline IDs (1-4) + locales (cg/en).

        // vehicle_type_id = 1
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 1)
            ->where('locale', 'cg')
            ->update(['description' => '4+1 do 7+1 sjedišta']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 1)
            ->where('locale', 'en')
            ->update(['description' => 'Passenger car (4+1 to 7+1 seats)']);

        // vehicle_type_id = 2
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 2)
            ->where('locale', 'cg')
            ->update(['description' => '8+1 sjedište']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 2)
            ->where('locale', 'en')
            ->update(['description' => '8+1 seats']);

        // vehicle_type_id = 3
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 3)
            ->where('locale', 'cg')
            ->update(['description' => '9–23 sjedišta']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 3)
            ->where('locale', 'en')
            ->update(['description' => '9–23 seats']);

        // vehicle_type_id = 4
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 4)
            ->where('locale', 'cg')
            ->update(['description' => 'preko 23 sjedišta']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 4)
            ->where('locale', 'en')
            ->update(['description' => 'over 23 seats']);
    }

    public function down(): void
    {
        // Restore previous descriptions as they existed before this refinement.

        // vehicle_type_id = 1
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 1)
            ->where('locale', 'cg')
            ->update(['description' => 'Automobil (4+1 do 7+1 sjedišta)']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 1)
            ->where('locale', 'en')
            ->update(['description' => 'Passenger car (4+1 to 7+1 seats)']);

        // vehicle_type_id = 2
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 2)
            ->where('locale', 'cg')
            ->update(['description' => 'Mini bus (8+1 sjedište)']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 2)
            ->where('locale', 'en')
            ->update(['description' => 'Mini bus (8+1 seats)']);

        // vehicle_type_id = 3
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 3)
            ->where('locale', 'cg')
            ->update(['description' => 'Autobus (9–23 sjedišta)']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 3)
            ->where('locale', 'en')
            ->update(['description' => 'Bus (9–23 seats)']);

        // vehicle_type_id = 4
        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 4)
            ->where('locale', 'cg')
            ->update(['description' => 'Autobus (preko 23 sjedišta)']);

        DB::table('vehicle_type_translations')
            ->where('vehicle_type_id', 4)
            ->where('locale', 'en')
            ->update(['description' => 'Bus (over 23 seats)']);
    }
};


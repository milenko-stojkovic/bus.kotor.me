<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleTypeTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('vehicle_type_translations')->insert([
            ['vehicle_type_id' => 1, 'locale' => 'en', 'name' => 'Personal vehicle', 'description' => 'Personal vehicle (4+1, 5+1, 6+1 and 7+1 seats)'],
            ['vehicle_type_id' => 1, 'locale' => 'cg', 'name' => 'Putničko vozilo', 'description' => 'Putničko vozilo (4+1, 5+1, 6+1 i 7+1 mjesta)'],
            ['vehicle_type_id' => 2, 'locale' => 'en', 'name' => 'Mini bus', 'description' => 'Mini bus (8+1 seats)'],
            ['vehicle_type_id' => 2, 'locale' => 'cg', 'name' => 'Mini bus', 'description' => 'Mini bus (8+1 mjesta)'],
            ['vehicle_type_id' => 3, 'locale' => 'en', 'name' => 'Medium bus', 'description' => 'Medium bus (9–23 seats)'],
            ['vehicle_type_id' => 3, 'locale' => 'cg', 'name' => 'Srednji autobus', 'description' => 'Autobus (9–23 mjesta)'],
            ['vehicle_type_id' => 4, 'locale' => 'en', 'name' => 'Big bus', 'description' => 'Big bus (over 23 seats)'],
            ['vehicle_type_id' => 4, 'locale' => 'cg', 'name' => 'Veliki autobus', 'description' => 'Autobus (više od 23 mjesta)'],
        ]);
    }
}
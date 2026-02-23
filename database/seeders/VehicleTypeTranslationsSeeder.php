<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleTypeTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('vehicle_type_translations')->insert([
            ['vehicle_type_id' => 1, 'locale' => 'en', 'name' => 'Personal vehicle', 'description' => 'Passenger car (4+1 to 7+1 seats)'],
            ['vehicle_type_id' => 1, 'locale' => 'cg', 'name' => 'Putničko vozilo', 'description' => 'Automobil (4+1 do 7+1 sjedišta)'],
            ['vehicle_type_id' => 2, 'locale' => 'en', 'name' => 'Mini bus', 'description' => 'Mini bus (8+1 seats)'],
            ['vehicle_type_id' => 2, 'locale' => 'cg', 'name' => 'Mini bus', 'description' => 'Mini bus (8+1 sjedište)'],
            ['vehicle_type_id' => 3, 'locale' => 'en', 'name' => 'Medium bus', 'description' => 'Bus (9–23 seats)'],
            ['vehicle_type_id' => 3, 'locale' => 'cg', 'name' => 'Srednji autobus', 'description' => 'Autobus (9–23 sjedišta)'],
            ['vehicle_type_id' => 4, 'locale' => 'en', 'name' => 'Big bus', 'description' => 'Bus (over 23 seats)'],
            ['vehicle_type_id' => 4, 'locale' => 'cg', 'name' => 'Veliki autobus', 'description' => 'Autobus (preko 23 sjedišta)'],
        ]);
    }
}
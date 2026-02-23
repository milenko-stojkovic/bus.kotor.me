<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleTypesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('vehicle_types')->insert([
            ['price' => 15.00],
            ['price' => 20.00],
            ['price' => 40.00],
            ['price' => 50.00],
        ]);
    }
}
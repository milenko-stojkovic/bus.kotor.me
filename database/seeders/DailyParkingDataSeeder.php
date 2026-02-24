<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyParkingDataSeeder extends Seeder
{
    public function run(): void
    {
        $daysAhead = 90;

        $slots = DB::table('list_of_time_slots')->pluck('id')->toArray();
        $capacity = (int) DB::table('system_config')
            ->where('name', 'available_parking_slots')
            ->value('value');

        $rows = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = Carbon::today()->addDays($i)->toDateString();

            foreach ($slots as $slotId) {
                $rows[] = [
                    'date' => $date,
                    'time_slot_id' => $slotId,
                    'capacity' => in_array($slotId, [1, 41]) ? 999 : $capacity,
                    'reserved' => 0,
                    'pending' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('daily_parking_data')->insert($chunk);
        }
    }
}
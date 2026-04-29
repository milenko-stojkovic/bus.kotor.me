<?php

namespace Tests\Feature\Console;

use App\Models\ListOfTimeSlot;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TempDataCleanupRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_only_old_non_pending_rows_and_keeps_pending(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 29, 12, 0, 0));
        config()->set('reservations.temp_data_retention_days', 180);

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $vt = VehicleType::query()->create(['price' => 10]);

        $old = Carbon::now()->subDays(181)->toDateTimeString();
        $recent = Carbon::now()->subDays(10)->toDateTimeString();

        DB::table('temp_data')->insert([
            [
                'merchant_transaction_id' => 'mt-clean-old-failed',
                'retry_token' => null,
                'drop_off_time_slot_id' => $slot->id,
                'pick_up_time_slot_id' => $slot->id,
                'reservation_date' => Carbon::now()->addDay()->toDateString(),
                'user_name' => 'X',
                'country' => 'ME',
                'license_plate' => 'KO1',
                'vehicle_type_id' => $vt->id,
                'email' => 'x@example.com',
                // SQLite schema historically allows pending|failed|late_success; keep test compatible.
                'status' => 'failed',
                'created_at' => $old,
                'updated_at' => $old,
            ],
            [
                'merchant_transaction_id' => 'mt-clean-old-late',
                'retry_token' => null,
                'drop_off_time_slot_id' => $slot->id,
                'pick_up_time_slot_id' => $slot->id,
                'reservation_date' => Carbon::now()->addDay()->toDateString(),
                'user_name' => 'Y',
                'country' => 'ME',
                'license_plate' => 'KO2',
                'vehicle_type_id' => $vt->id,
                'email' => 'y@example.com',
                'status' => 'late_success',
                'created_at' => $old,
                'updated_at' => $old,
            ],
            [
                'merchant_transaction_id' => 'mt-clean-old-pending',
                'retry_token' => null,
                'drop_off_time_slot_id' => $slot->id,
                'pick_up_time_slot_id' => $slot->id,
                'reservation_date' => Carbon::now()->addDay()->toDateString(),
                'user_name' => 'P',
                'country' => 'ME',
                'license_plate' => 'KO3',
                'vehicle_type_id' => $vt->id,
                'email' => 'p@example.com',
                'status' => 'pending',
                'created_at' => $old,
                'updated_at' => $old,
            ],
            [
                'merchant_transaction_id' => 'mt-clean-recent-failed',
                'retry_token' => null,
                'drop_off_time_slot_id' => $slot->id,
                'pick_up_time_slot_id' => $slot->id,
                'reservation_date' => Carbon::now()->addDay()->toDateString(),
                'user_name' => 'R',
                'country' => 'ME',
                'license_plate' => 'KO4',
                'vehicle_type_id' => $vt->id,
                'email' => 'r@example.com',
                'status' => 'failed',
                'created_at' => $recent,
                'updated_at' => $recent,
            ],
        ]);

        $exit = Artisan::call('temp-data:cleanup');
        $this->assertSame(0, $exit);

        $this->assertDatabaseMissing('temp_data', ['merchant_transaction_id' => 'mt-clean-old-failed']);
        $this->assertDatabaseMissing('temp_data', ['merchant_transaction_id' => 'mt-clean-old-late']);

        $this->assertDatabaseHas('temp_data', ['merchant_transaction_id' => 'mt-clean-old-pending']);
        $this->assertDatabaseHas('temp_data', ['merchant_transaction_id' => 'mt-clean-recent-failed']);
    }
}


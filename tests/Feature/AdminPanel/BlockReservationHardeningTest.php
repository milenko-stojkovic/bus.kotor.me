<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\BlockZoneWorklist;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockReservationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_created_by_admin_defaults_to_false_when_omitted(): void
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $vt = VehicleType::query()->create(['price' => 5]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Car',
                'description' => null,
            ]);
        }

        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-created-by-default',
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'user_name' => 'T',
            'country' => 'ME',
            'license_plate' => 'AB123',
            'vehicle_type_id' => $vt->id,
            'email' => 't@example.com',
            'status' => 'paid',
            'invoice_amount' => '5.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->assertFalse((bool) $r->fresh()->created_by_admin);
    }

    public function test_post_lock_validation_rejects_when_new_day_slot_has_no_capacity(): void
    {
        $admin = Admin::query()->create([
            'username' => 'blockharden',
            'email' => 'block-harden@example.com',
            'password' => bcrypt('secret-password-5'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $d1 = Carbon::now()->addDay()->toDateString();
        $d2 = Carbon::now()->addDays(2)->toDateString();

        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $s3 = ListOfTimeSlot::query()->create(['time_slot' => '12:00 - 12:20']);
        $vt = VehicleType::query()->create(['price' => 5]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Car',
                'description' => null,
            ]);
        }

        foreach ([$d1, $d2] as $date) {
            foreach ([$s1, $s2, $s3] as $slot) {
                DailyParkingData::query()->create([
                    'date' => $date,
                    'time_slot_id' => $slot->id,
                    'capacity' => 5,
                    'reserved' => 0,
                    'pending' => 0,
                    'is_blocked' => false,
                ]);
            }
        }

        DailyParkingData::query()
            ->where('date', $d1)
            ->where('time_slot_id', $s1->id)
            ->update(['reserved' => 1]);
        DailyParkingData::query()
            ->where('date', $d1)
            ->where('time_slot_id', $s2->id)
            ->update(['reserved' => 1]);

        DailyParkingData::query()
            ->where('date', $d2)
            ->where('time_slot_id', $s1->id)
            ->update(['capacity' => 1, 'reserved' => 1]);

        $mtid = 'mt-adjust-lock-test';
        $reservation = Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $s1->id,
            'pick_up_time_slot_id' => $s2->id,
            'reservation_date' => $d1,
            'user_name' => 'T',
            'country' => 'ME',
            'license_plate' => 'AB123',
            'vehicle_type_id' => $vt->id,
            'email' => 't@example.com',
            'status' => 'paid',
            'invoice_amount' => '5.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $row = BlockZoneWorklist::query()->create([
            'merchant_transaction_id' => $mtid,
            'status' => BlockZoneWorklist::STATUS_READY_TO_ADJUST,
            'old_date' => $d1,
            'old_drop_off' => $s1->id,
            'old_pick_up' => $s2->id,
            'affected_drop_off' => true,
            'affected_pick_up' => true,
            'snapshot_json' => ['user_name' => 'T', 'email' => 't@example.com'],
            'reservation_id' => $reservation->id,
            'temp_data_id' => null,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.blocking.worklist.adjust.apply', $row, false), [
            'new_date' => $d2,
            'new_drop_off' => $s1->id,
            'new_pick_up' => $s2->id,
        ])->assertSessionHas('error');

        $this->assertSame($d1, $reservation->fresh()->reservation_date->toDateString());
        $this->assertTrue(BlockZoneWorklist::query()->whereKey($row->id)->exists());
    }

    public function test_apply_block_redirect_includes_fresh_cache_bust_query(): void
    {
        $admin = Admin::query()->create([
            'username' => 'blockfresh',
            'email' => 'block-fresh@example.com',
            'password' => bcrypt('secret-password-6'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $date = Carbon::now()->addDay()->toDateString();

        $this->actingAs($admin, 'panel_admin');

        $response = $this->post(route('panel_admin.blocking.apply', [], false), [
            'date' => $date,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('_fresh=', $location);
        $this->assertStringContainsString('date=', $location);
    }
}

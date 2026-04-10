<?php

namespace Tests\Feature\AdminPanel;

use App\Models\AdminAlert;
use App\Models\ListOfTimeSlot;
use App\Models\TempData;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminFiscalizationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminFiscalizationAlertPersistsDbTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_payment_success_after_canceled_creates_admin_alert_record(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite test schema may not align with temp_data.status canceled (MySQL ENUM migrations are guarded).');
        }

        Mail::fake();

        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 5]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Car',
                'description' => null,
            ]);
        }

        $temp = TempData::query()->create([
            'merchant_transaction_id' => 'tx-persist-alert-1',
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'user_name' => 'Test User',
            'country' => 'ME',
            'license_plate' => 'ABC123',
            'vehicle_type_id' => $vt->id,
            'email' => 'guest@example.com',
            'status' => TempData::STATUS_CANCELED,
            'resolution_reason' => 'test_cancel',
        ]);

        app(AdminFiscalizationAlertService::class)->notifyPaymentSuccessAfterCanceled($temp->fresh(), ['source' => 'test_incoming']);

        $this->assertSame(1, AdminAlert::query()->where('type', 'payment_success_after_canceled')->count());
        $row = AdminAlert::query()->first();
        $this->assertSame(AdminAlert::STATUS_UNREAD, $row->status);
        $this->assertNotNull($row->payload_json);
        $this->assertArrayHasKey('email_full_body', $row->payload_json);
    }
}

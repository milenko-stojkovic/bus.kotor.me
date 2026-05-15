<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\LimoIncident;
use App\Models\LimoPickupEvent;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\Limo\LimoPickupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LimoAdminIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_panel_access_sees_limo_overview(): void
    {
        $admin = $this->makePanelAdmin();

        $response = $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.index', [], false));

        $response->assertOk()
            ->assertSee('Limo događaji', false);
    }

    public function test_limo_access_only_admin_cannot_access_admin_limo_page(): void
    {
        $admin = Admin::query()->create([
            'username' => 'limo_only_ops',
            'email' => 'limo-only@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.index', [], false))
            ->assertForbidden();
    }

    public function test_date_filter_limits_rows_by_occurred_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'Europe/Podgorica'));

        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->seedLimoEvent(Carbon::parse('2026-05-08 10:00:00', 'Europe/Podgorica'), 'Agency A');
        $this->seedLimoEvent(Carbon::parse('2026-05-12 15:00:00', 'Europe/Podgorica'), 'Agency B');

        $this->get(route('admin.limo.index', [
            'date_from' => '2026-05-08',
            'date_to' => '2026-05-10',
            'type' => 'pickup',
        ], false))
            ->assertOk()
            ->assertSee('Agency A', false)
            ->assertDontSee('Agency B', false);

        Carbon::setTestNow();
    }

    public function test_default_type_pickup_shows_pickup_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'Europe/Podgorica'));

        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->seedLimoEvent(Carbon::parse('2026-05-10 10:00:00', 'Europe/Podgorica'), 'Pickup Ag');

        $this->get(route('admin.limo.index', [], false))
            ->assertOk()
            ->assertSee('Pickup Ag', false)
            ->assertSee('Iznos', false);

        $this->get(route('admin.limo.index', ['type' => 'pickup'], false))
            ->assertOk()
            ->assertSee('Pickup Ag', false);

        Carbon::setTestNow();
    }

    public function test_type_incident_shows_incident_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'Europe/Podgorica'));

        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        $recorder = Admin::query()->create([
            'username' => 'inc_rec_idx',
            'email' => 'inc-rec-idx@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $uuid = (string) Str::uuid();
        $platePath = 'limo_incidents/'.$uuid.'/plate.jpg';
        LimoIncident::query()->create([
            'incident_uuid' => $uuid,
            'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
            'license_plate_snapshot' => 'KOINC1',
            'agency_user_id' => null,
            'agency_name_snapshot' => null,
            'agency_email_snapshot' => null,
            'visible_agency_name' => 'Brand X',
            'plate_photo_path' => $platePath,
            'branding_photo_path' => null,
            'note' => 'Incident bilješka jedinstvena',
            'occurred_at' => Carbon::parse('2026-05-10 11:00:00', 'Europe/Podgorica'),
            'gps_lat' => null,
            'gps_lng' => null,
            'recorded_by_limo_admin_id' => $recorder->id,
            'device_info' => null,
            'communal_email_sent_at' => null,
            'admin_alert_id' => null,
        ]);

        $this->get(route('admin.limo.index', ['type' => 'incident'], false))
            ->assertOk()
            ->assertSee('license_plate_snapshot', false)
            ->assertSee('KOINC1', false)
            ->assertSee('Incident bilješka jedinstvena', false);

        Carbon::setTestNow();
    }

    public function test_date_filter_applies_to_incident_list(): void
    {
        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        $recorder = Admin::query()->create([
            'username' => 'inc_rec_dt',
            'email' => 'inc-rec-dt@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $mk = function (string $suffix, Carbon $at) use ($recorder): void {
            $uuid = (string) Str::uuid();
            LimoIncident::query()->create([
                'incident_uuid' => $uuid,
                'type' => LimoIncident::TYPE_DRIVER_NON_COOPERATIVE,
                'license_plate_snapshot' => $suffix,
                'agency_user_id' => null,
                'agency_name_snapshot' => null,
                'agency_email_snapshot' => null,
                'visible_agency_name' => null,
                'plate_photo_path' => 'limo_incidents/'.$uuid.'/p.jpg',
                'branding_photo_path' => null,
                'note' => null,
                'occurred_at' => $at,
                'gps_lat' => null,
                'gps_lng' => null,
                'recorded_by_limo_admin_id' => $recorder->id,
                'device_info' => null,
                'communal_email_sent_at' => null,
                'admin_alert_id' => null,
            ]);
        };

        $mk('IN_RANGE', Carbon::parse('2026-05-09 12:00:00', 'Europe/Podgorica'));
        $mk('OUT_RANGE', Carbon::parse('2026-05-20 12:00:00', 'Europe/Podgorica'));

        $this->get(route('admin.limo.index', [
            'type' => 'incident',
            'date_from' => '2026-05-08',
            'date_to' => '2026-05-10',
        ], false))
            ->assertOk()
            ->assertSee('IN_RANGE', false)
            ->assertDontSee('OUT_RANGE', false);
    }

    public function test_nav_label_limo_dogadjaji_on_admin_layout(): void
    {
        $admin = $this->makePanelAdmin();
        $html = $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.index', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Limo događaji', $html);
    }

    public function test_page_lists_only_limo_pickup_events_not_reservations(): void
    {
        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $vt = VehicleType::query()->create(['price' => 10]);

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-res-not-limo-xyz',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => Carbon::now()->addDay()->toDateString(),
            'user_name' => 'Guest Name Unique',
            'country' => 'ME',
            'license_plate' => 'KO999ZZ',
            'vehicle_type_id' => $vt->id,
            'email' => 'reservation-only-limo-test@example.com',
            'status' => 'paid',
            'invoice_amount' => '25.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);

        $this->seedLimoEvent(now(), 'Agencija Limo Samo');

        $this->get(route('admin.limo.index', [], false))
            ->assertOk()
            ->assertSee('Agencija Limo Samo', false)
            ->assertDontSee('reservation-only-limo-test@example.com', false)
            ->assertDontSee('mt-res-not-limo-xyz', false);
    }

    public function test_table_shows_expected_column_headers(): void
    {
        $admin = $this->makePanelAdmin();

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.index', [], false))
            ->assertOk()
            ->assertSee('Datum i vrijeme', false)
            ->assertSee('Agencija', false)
            ->assertSee('Tablica', false)
            ->assertSee('Iznos', false)
            ->assertSee('Izvor', false)
            ->assertSee('Status', false)
            ->assertSee('JIR', false)
            ->assertSee('Slika', false);
    }

    private function makePanelAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'panel_limo_index',
            'email' => 'panel-limo-index@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);
    }

    private function seedLimoEvent(Carbon $occurredAt, string $agencyName): LimoPickupEvent
    {
        $recorder = Admin::query()->create([
            'username' => 'limo_rec_'.Str::random(6),
            'email' => 'limo-rec-'.Str::random(6).'@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $user = User::factory()->create([
            'name' => $agencyName,
            'email' => 'agency-'.Str::random(4).'@test.local',
            'country' => 'ME',
        ]);

        return LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => $agencyName,
            'agency_email_snapshot' => $user->email,
            'agency_country_snapshot' => 'ME',
            'source' => 'qr',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO123AB',
            'amount_snapshot' => '15.00',
            'service_name_snapshot' => LimoPickupService::SERVICE_NAME,
            'occurred_at' => $occurredAt,
            'recorded_by_limo_admin_id' => $recorder->id,
            'status' => 'pending_fiscal',
        ]);
    }
}

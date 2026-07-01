<?php

namespace Tests\Feature\AdminPanel;

use App\Jobs\SendAdminUpdatedReservationDocumentJob;
use App\Jobs\SendFreeReservationConfirmationJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPanelReservationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'resvadmin',
            'email' => 'resv-admin@example.com',
            'password' => bcrypt('secret-password-rv'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    /**
     * @return array{s1: ListOfTimeSlot, s2: ListOfTimeSlot, s3: ListOfTimeSlot, vt: VehicleType}
     */
    private function seedVehicleAndThreeSlots(): array
    {
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $s3 = ListOfTimeSlot::query()->create(['time_slot' => '12:00 - 12:20']);
        $vt = VehicleType::query()->create(['price' => 15]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Bus',
                'description' => null,
            ]);
        }

        return ['s1' => $s1, 's2' => $s2, 's3' => $s3, 'vt' => $vt];
    }

    /**
     * @param  list<ListOfTimeSlot>  $slots
     */
    private function seedDailyForDate(string $date, array $slots): void
    {
        foreach ($slots as $slot) {
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

    public function test_guest_is_redirected_from_reservations_index(): void
    {
        $this->get(route('panel_admin.reservations', [], false))
            ->assertRedirect();
    }

    public function test_admin_can_open_reservations_search_page(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [], false))
            ->assertOk()
            ->assertSee('Rezervacije', false)
            ->assertSee('name="license_plate"', false)
            ->assertSee('id="license_plate"', false)
            ->assertSee('for="license_plate"', false)
            ->assertSee('this.value=this.value.toUpperCase().replace(/[^A-Z0-9]+/g', false);
    }

    public function test_search_result_for_agency_reservation_shows_agency_type_and_account(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create([
            'name' => 'Omnibus doo',
            'email' => 'omnibus@example.com',
        ]);
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-admin-agency-type-'.uniqid();
        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Omnibus doo',
            'country' => 'ME',
            'license_plate' => 'KO111AG',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'reservation@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $html = $this->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee('PDF', false)
            ->assertSee('Izmeni', false)
            ->getContent();

        $this->assertStringContainsString('Tip korisnika:', $html);
        $this->assertStringContainsString('Agencija', $html);
        $this->assertStringContainsString('Omnibus doo', $html);
        $this->assertStringContainsString('Email naloga:', $html);
        $this->assertStringContainsString('omnibus@example.com', $html);
        $this->assertStringContainsString('Email rezervacije:', $html);
        $this->assertStringContainsString('reservation@example.com', $html);
    }

    public function test_search_result_for_guest_reservation_shows_guest_type_and_contact(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-admin-guest-type-'.uniqid();
        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Marko Marković',
            'country' => 'ME',
            'license_plate' => 'KO222GU',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'marko@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $html = $this->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee('PDF', false)
            ->assertSee('Izmeni', false)
            ->getContent();

        $this->assertStringContainsString('Tip korisnika:', $html);
        $this->assertStringContainsString('Guest', $html);
        $this->assertStringContainsString('Marko Marković', $html);
        $this->assertStringContainsString('marko@example.com', $html);
        $this->assertStringNotContainsString('Email naloga:', $html);
    }

    public function test_nova_pretraga_link_appears_when_filters_applied_and_points_to_clean_route(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-admin-nova-pretraga-'.uniqid();
        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Nova Pretraga Test',
            'country' => 'ME',
            'license_plate' => 'KO333NP',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'nova-pretraga@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $searchUrl = route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false);
        $cleanUrl = route('panel_admin.reservations', [], false);

        $html = $this->get($searchUrl)
            ->assertOk()
            ->assertSee('Nova pretraga', false)
            ->assertSee('PDF', false)
            ->assertSee('Izmeni', false)
            ->getContent();

        $this->assertStringContainsString('href="'.$cleanUrl.'"', $html);

        $this->get($cleanUrl)
            ->assertOk()
            ->assertDontSee('Nova pretraga', false)
            ->assertDontSee('Rezultati', false);
    }

    public function test_search_by_merchant_transaction_id_returns_reservation(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-admin-search-unique-'.uniqid();
        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Test User',
            'country' => 'ME',
            'license_plate' => 'KO999AA',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'search@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        DailyParkingData::query()
            ->whereDate('date', $d)
            ->whereIn('time_slot_id', [$slots['s1']->id, $slots['s2']->id])
            ->increment('reserved');

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee($mtid, false)
            ->assertSee('Rezultati', false);
    }

    public function test_edit_returns_403_for_realized_reservation(): void
    {
        $admin = $this->seedAdmin();
        $past = Carbon::now()->subDay()->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($past, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-past-realized',
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $past,
            'user_name' => 'Past',
            'country' => 'ME',
            'license_plate' => 'AA111',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'past@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations.edit', ['reservation' => $r], false))
            ->assertForbidden();
    }

    public function test_realized_reservation_in_search_shows_detail_link_to_show_page(): void
    {
        $admin = $this->seedAdmin();
        $past = Carbon::now()->subDay()->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($past, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-past-detail-link';
        $r = Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $past,
            'user_name' => 'Past Detail',
            'country' => 'ME',
            'license_plate' => 'AADET1',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'past-detail@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $showUrl = route('panel_admin.reservations.show', [
            'reservation' => $r->id,
            'rq' => 'merchant_transaction_id='.$mtid,
        ], false);
        $showUrlHtml = str_replace('&', '&amp;', $showUrl);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee('Detalj', false)
            ->assertSee($showUrlHtml, false)
            ->assertDontSee('title="Realizovana rezervacija"', false);

        $this->actingAs($admin, 'panel_admin')
            ->get($showUrl)
            ->assertOk()
            ->assertSee('Past Detail', false)
            ->assertSee('AADET1', false);
    }

    public function test_admin_can_update_reservation_and_dispatch_invoice_job(): void
    {
        Queue::fake();

        $admin = $this->seedAdmin();
        $d1 = Carbon::now()->addDay()->toDateString();
        $d2 = Carbon::now()->addDays(2)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d1, [$slots['s1'], $slots['s2'], $slots['s3']]);
        $this->seedDailyForDate($d2, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-update-'.uniqid(),
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d1,
            'user_name' => 'Move Me',
            'country' => 'ME',
            'license_plate' => 'KO111AA',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'move@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_SENT,
        ]);

        DailyParkingData::query()
            ->whereDate('date', $d1)
            ->whereIn('time_slot_id', [$slots['s1']->id, $slots['s2']->id])
            ->increment('reserved');

        $this->actingAs($admin, 'panel_admin');

        $rq = http_build_query(['merchant_transaction_id' => $r->merchant_transaction_id]);

        $response = $this->put(route('panel_admin.reservations.update', $r, false), [
            'reservation_date' => $d2,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s3']->id,
            'user_name' => 'Move Me',
            'country' => 'ME',
            'license_plate' => 'KO222BB',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'move@example.com',
            'return_query' => $rq,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('rezervacije', $location);
        $this->assertStringContainsString('merchant_transaction_id', $location);

        $r->refresh();
        $this->assertSame($d2, $r->reservation_date->toDateString());
        $this->assertSame($slots['s3']->id, $r->pick_up_time_slot_id);
        $this->assertSame('KO222BB', $r->license_plate);
        $this->assertNull($r->invoice_sent_at);
        $this->assertSame(Reservation::EMAIL_NOT_SENT, (int) $r->email_sent);

        $this->assertSame(0, (int) DailyParkingData::query()
            ->whereDate('date', $d1)
            ->where('time_slot_id', $slots['s1']->id)
            ->value('reserved'));
        $this->assertSame(0, (int) DailyParkingData::query()
            ->whereDate('date', $d1)
            ->where('time_slot_id', $slots['s2']->id)
            ->value('reserved'));
        $this->assertSame(1, (int) DailyParkingData::query()
            ->whereDate('date', $d2)
            ->where('time_slot_id', $slots['s1']->id)
            ->value('reserved'));
        $this->assertSame(1, (int) DailyParkingData::query()
            ->whereDate('date', $d2)
            ->where('time_slot_id', $slots['s3']->id)
            ->value('reserved'));

        Queue::assertPushed(SendAdminUpdatedReservationDocumentJob::class);
        Queue::assertNotPushed(SendInvoiceEmailJob::class);
        Queue::assertNotPushed(SendFreeReservationConfirmationJob::class);
    }

    public function test_admin_update_free_reservation_dispatches_confirmation_job(): void
    {
        Queue::fake();

        $admin = $this->seedAdmin();
        $d1 = Carbon::now()->addDay()->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d1, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $r = Reservation::query()->create([
            'merchant_transaction_id' => null,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d1,
            'user_name' => 'Free User',
            'country' => 'ME',
            'license_plate' => 'FR111',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'free@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_SENT,
            'created_by_admin' => true,
        ]);

        DailyParkingData::query()
            ->whereDate('date', $d1)
            ->whereIn('time_slot_id', [$slots['s1']->id, $slots['s2']->id])
            ->increment('reserved');

        $this->actingAs($admin, 'panel_admin');

        $this->put(route('panel_admin.reservations.update', $r, false), [
            'reservation_date' => $d1,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'user_name' => 'Free User Updated',
            'country' => 'ME',
            'license_plate' => 'FR222',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'free@example.com',
            'return_query' => '',
        ])->assertRedirect();

        $this->assertSame('Free User Updated', $r->fresh()->user_name);

        Queue::assertPushed(SendAdminUpdatedReservationDocumentJob::class);
        Queue::assertNotPushed(SendInvoiceEmailJob::class);
        Queue::assertNotPushed(SendFreeReservationConfirmationJob::class);
    }

    public function test_admin_search_renders_daily_ticket_without_slot_crash(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(5)->toDateString();
        $vt = VehicleType::query()->create(['price' => 20]);
        $mtid = 'mt-admin-daily-'.uniqid();

        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $d,
            'user_name' => 'Daily Agency',
            'country' => 'ME',
            'license_plate' => 'KO777DD',
            'vehicle_type_id' => $vt->id,
            'email' => 'daily-admin@test.local',
            'status' => 'paid',
            'invoice_amount' => '20.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee('Dnevna naknada', false)
            ->assertSee('Autoboka i Puč', false)
            ->assertDontSee('Vrijeme dolaska', false);
    }

    public function test_admin_can_update_daily_ticket_safe_fields(): void
    {
        Queue::fake();

        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(4)->toDateString();
        $vt = VehicleType::query()->create(['price' => 20]);

        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-admin-daily-edit',
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $d,
            'user_name' => 'Daily Agency',
            'country' => 'ME',
            'license_plate' => 'KO777DD',
            'vehicle_type_id' => $vt->id,
            'email' => 'daily-admin@test.local',
            'status' => 'paid',
            'invoice_amount' => '20.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations.edit', ['reservation' => $r], false))
            ->assertOk()
            ->assertSee('Dnevna naknada', false)
            ->assertSee('Sačuvaj', false);

        $this->put(route('panel_admin.reservations.update', $r, false), [
            'reservation_date' => $d,
            'user_name' => 'Daily Updated',
            'country' => 'ME',
            'license_plate' => 'KO888EE',
            'vehicle_type_id' => $vt->id,
            'email' => 'daily-updated@test.local',
            'return_query' => '',
        ])->assertRedirect();

        $r->refresh();
        $this->assertSame('Daily Updated', $r->user_name);
        $this->assertSame('KO888EE', $r->license_plate);
        Queue::assertPushed(SendAdminUpdatedReservationDocumentJob::class);
    }

    public function test_reservation_search_normalizes_license_plate_with_spaces(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-plate-spaces-'.uniqid(),
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Plate Spaces',
            'country' => 'ME',
            'license_plate' => 'ZG123AB',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'plate-spaces@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['license_plate' => 'zg 123 ab'], false))
            ->assertOk()
            ->assertSee('ZG123AB', false);
    }

    public function test_reservation_search_normalizes_license_plate_with_symbols(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-plate-symbols-'.uniqid(),
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Plate Symbols',
            'country' => 'ME',
            'license_plate' => 'ZG123AB',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'plate-symbols@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['license_plate' => 'zg-123/ab'], false))
            ->assertOk()
            ->assertSee('ZG123AB', false);
    }

    public function test_reservation_search_accepts_lowercase_license_plate_input(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-plate-lower-'.uniqid(),
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Plate Lower',
            'country' => 'ME',
            'license_plate' => 'KO111AG',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'plate-lower@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['license_plate' => 'ko111ag'], false))
            ->assertOk()
            ->assertSee('KO111AG', false);
    }

    public function test_reservation_search_by_merchant_transaction_id_still_works(): void
    {
        $admin = $this->seedAdmin();
        $d = Carbon::now()->addDays(3)->toDateString();
        $slots = $this->seedVehicleAndThreeSlots();
        $this->seedDailyForDate($d, [$slots['s1'], $slots['s2'], $slots['s3']]);

        $mtid = 'mt-search-still-works-'.uniqid();
        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'MTID Search',
            'country' => 'ME',
            'license_plate' => 'KO555ZZ',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'mtid-search@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', ['merchant_transaction_id' => $mtid], false))
            ->assertOk()
            ->assertSee('KO555ZZ', false)
            ->assertSee('MTID Search', false);
    }

    /**
     * @return array{s1: ListOfTimeSlot, s2: ListOfTimeSlot, vt: VehicleType}
     */
    private function seedSlotsForAgencySearch(): array
    {
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 15]);

        return ['s1' => $s1, 's2' => $s2, 'vt' => $vt];
    }

    public function test_agency_search_finds_reservations_by_user_id_when_snapshot_name_differs(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create([
            'name' => 'Guliver Montenegro',
            'email' => 'milica@guliver.me',
            'country' => 'ME',
        ]);
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $mtid = 'mt-agency-mismatch-'.uniqid();

        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Guliver DOO',
            'country' => 'ME',
            'license_plate' => 'KO901GM',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'booking@guliver.me',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [
            'agency_user_id' => $agency->id,
            'name' => 'Guliver Montenegro',
            'email' => 'milica@guliver.me',
            'country' => 'ME',
        ], false))
            ->assertOk()
            ->assertSee('Rezultati', false)
            ->assertSee($mtid, false);
    }

    public function test_agency_search_does_not_require_clearing_autofilled_name(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create([
            'name' => 'DOO MONTENEGRO CRUSING',
            'email' => 'office@montenegrocrusing.com',
        ]);
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $mtid = 'mt-agency-no-clear-'.uniqid();

        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Montenegro Cruising',
            'country' => 'ME',
            'license_plate' => 'KO902MC',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'office@montenegrocrusing.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [
            'agency_user_id' => $agency->id,
            'name' => 'DOO MONTENEGRO CRUSING',
        ], false))
            ->assertOk()
            ->assertSee($mtid, false);
    }

    public function test_agency_search_still_works_when_snapshot_name_matches_account_name(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create([
            'name' => 'Dalmamont',
            'email' => 'info@dalmamont.me',
        ]);
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $mtid = 'mt-agency-match-'.uniqid();

        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Dalmamont',
            'country' => 'ME',
            'license_plate' => 'KO903DM',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'info@dalmamont.me',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [
            'agency_user_id' => $agency->id,
            'name' => 'Dalmamont',
            'email' => 'info@dalmamont.me',
        ], false))
            ->assertOk()
            ->assertSee($mtid, false);
    }

    public function test_guest_search_by_name_email_and_country_still_works(): void
    {
        $admin = $this->seedAdmin();
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $mtid = 'mt-guest-contact-'.uniqid();

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Guest Traveler',
            'country' => 'HR',
            'license_plate' => 'KO904GU',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'guest-traveler@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [
            'name' => 'Guest',
            'email' => 'guest-traveler@example.com',
            'country' => 'HR',
        ], false))
            ->assertOk()
            ->assertSee($mtid, false);
    }

    public function test_manual_filters_without_agency_selection_still_works(): void
    {
        $admin = $this->seedAdmin();
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $mtid = 'mt-manual-only-'.uniqid();

        Reservation::query()->create([
            'merchant_transaction_id' => $mtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Manual Filter Only',
            'country' => 'ME',
            'license_plate' => 'KO905MF',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'manual-only@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reservations', [
            'name' => 'Manual',
            'email' => 'manual-only@example.com',
        ], false))
            ->assertOk()
            ->assertSee($mtid, false);
    }

    public function test_agency_selection_does_not_include_guest_reservation_with_same_email(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create([
            'name' => 'Shared Email Agency',
            'email' => 'shared@example.com',
        ]);
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();

        $agencyMtid = 'mt-agency-shared-'.uniqid();
        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => $agencyMtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Agency Snapshot',
            'country' => 'ME',
            'license_plate' => 'KO906AG',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'shared@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $guestMtid = 'mt-guest-shared-'.uniqid();
        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => $guestMtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Guest With Shared Email',
            'country' => 'ME',
            'license_plate' => 'KO907GU',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'shared@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $html = $this->get(route('panel_admin.reservations', [
            'agency_user_id' => $agency->id,
            'email' => 'shared@example.com',
        ], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($agencyMtid, $html);
        $this->assertStringNotContainsString($guestMtid, $html);
    }

    public function test_migrated_guest_reservation_not_returned_by_agency_user_id_filter(): void
    {
        $admin = $this->seedAdmin();
        $agency = User::factory()->create(['email' => 'agency-only@example.com']);
        $slots = $this->seedSlotsForAgencySearch();
        $d = Carbon::now()->addDays(4)->toDateString();
        $guestMtid = 'mt-v1-guest-only-'.uniqid();

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => $guestMtid,
            'drop_off_time_slot_id' => $slots['s1']->id,
            'pick_up_time_slot_id' => $slots['s2']->id,
            'reservation_date' => $d,
            'user_name' => 'Legacy Guest',
            'country' => 'ME',
            'license_plate' => 'KO908V1',
            'vehicle_type_id' => $slots['vt']->id,
            'email' => 'agency-only@example.com',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_at' => Carbon::parse('2020-01-01'),
            'updated_at' => Carbon::parse('2020-01-01'),
        ]);

        $this->actingAs($admin, 'panel_admin');

        $agencyHtml = $this->get(route('panel_admin.reservations', [
            'agency_user_id' => $agency->id,
            'email' => 'agency-only@example.com',
        ], false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($guestMtid, $agencyHtml);

        $this->get(route('panel_admin.reservations', [
            'email' => 'agency-only@example.com',
        ], false))
            ->assertOk()
            ->assertSee($guestMtid, false);
    }
}

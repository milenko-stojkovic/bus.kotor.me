<?php

namespace Tests\Feature\FreeRequest;

use App\Mail\FreeReservationRequestSubmittedMail;
use App\Models\AdminAlert;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestVehicle;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\TempData;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FreeReservationRequestIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_students_branch_link(): void
    {
        $html = $this->get(route('landing', [], false))->assertOk()->getContent();

        $this->assertStringContainsString(route('free-request.create', [], false), $html);
    }

    public function test_public_request_submit_creates_request_vehicles_sends_admin_mail_and_creates_warning(): void
    {
        Mail::fake();

        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $loc,
                'name' => 'VT',
                'description' => null,
            ]);
        }

        $d = now()->addDays(3)->toDateString();

        $this->post(route('free-request.store', [], false), [
            'reservation_date' => $d,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'institution_name' => 'OŠ Kotor',
            'country' => 'ME',
            'institution_email' => 'school@example.com',
            'institution_phone' => '+38267111222',
            'vehicles' => [
                ['license_plate' => 'ko-123-ab', 'vehicle_type_id' => $vt->id],
                ['license_plate' => 'KO999', 'vehicle_type_id' => $vt->id],
            ],
        ])->assertRedirect(route('free-request.success', [], false));

        $this->assertSame(0, Reservation::query()->count());
        $this->assertSame(0, TempData::query()->count());

        $req = FreeReservationRequest::query()->first();
        $this->assertNotNull($req);
        $this->assertSame('OŠ Kotor', $req->institution_name);
        $this->assertSame('school@example.com', $req->institution_email);
        $this->assertSame('+38267111222', $req->institution_phone);
        $this->assertSame($d, $req->reservation_date->toDateString());

        $vehicles = FreeReservationRequestVehicle::query()->where('request_id', $req->id)->orderBy('id')->get();
        $this->assertCount(2, $vehicles);
        $this->assertSame('KO123AB', $vehicles[0]->license_plate);

        Mail::assertSent(FreeReservationRequestSubmittedMail::class, function (FreeReservationRequestSubmittedMail $m) use ($req) {
            return $m->hasTo('bus@kotor.me') && $m->request->id === $req->id;
        });

        $alert = AdminAlert::query()->where('type', 'free_reservation_request')->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('OŠ Kotor', $alert->message);
        $this->assertSame($req->id, $alert->payload_json['free_reservation_request_id'] ?? null);
    }

    public function test_phone_validation_requires_plus_and_digits_only(): void
    {
        Mail::fake();

        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create(['vehicle_type_id' => $vt->id, 'locale' => 'cg', 'name' => 'VT', 'description' => null]);

        $this->post(route('free-request.store', [], false), [
            'reservation_date' => now()->addDays(3)->toDateString(),
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'institution_name' => 'X',
            'country' => 'ME',
            'institution_email' => 'x@example.com',
            'institution_phone' => '+382 67 111 222', // invalid: spaces
            'vehicles' => [
                ['license_plate' => 'KO123', 'vehicle_type_id' => $vt->id],
            ],
        ])->assertSessionHasErrors(['institution_phone']);

        $this->assertSame(0, FreeReservationRequest::query()->count());
        Mail::assertNothingSent();
    }
}


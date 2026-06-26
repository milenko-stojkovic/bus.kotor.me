<?php

namespace Tests\Feature\Email;

use App\Jobs\SendAdminUpdatedReservationDocumentJob;
use App\Jobs\SendFreeReservationConfirmationJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Mail\AdvanceTopupConfirmationMail;
use App\Mail\FreeReservationRequestFulfilledMail;
use App\Models\AgencyAdvanceTopup;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminPanel\FreeReservation\FreeReservationRequestFulfillmentService;
use App\Support\ReservationEmailReferenceLine;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

final class ReservationConfirmationEmailReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidReservation(array $overrides = []): Reservation
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 15]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Bus',
            'description' => null,
        ]);

        return Reservation::query()->create(array_merge([
            'merchant_transaction_id' => 'mt-paid-email-'.Str::random(8),
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'user_name' => 'Test User',
            'country' => 'ME',
            'license_plate' => 'KO123AB',
            'vehicle_type_id' => $vt->id,
            'email' => 'paid@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ], $overrides));
    }

    private function makeDailyTicketReservation(array $overrides = []): Reservation
    {
        $vt = VehicleType::query()->create(['price' => '40.00']);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Autobus',
            'description' => 'Opis',
        ]);

        return Reservation::query()->create(array_merge([
            'merchant_transaction_id' => 'mt-daily-email-'.Str::random(8),
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => now()->addDays(5)->toDateString(),
            'user_name' => 'Daily Agency',
            'country' => 'ME',
            'license_plate' => 'KO999ZZ',
            'vehicle_type_id' => $vt->id,
            'email' => 'daily@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => '40.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ], $overrides));
    }

    private function invokePaidInvoiceBody(Reservation $reservation, string $locale): string
    {
        $job = new SendInvoiceEmailJob($reservation->id, false);
        $method = new ReflectionMethod($job, 'buildConfirmationText');
        $method->setAccessible(true);

        return $method->invoke($job, $reservation->fresh(), $locale);
    }

    private function invokeFreeReservationBody(Reservation $reservation, string $locale): string
    {
        $job = new SendFreeReservationConfirmationJob($reservation->id);
        $method = new ReflectionMethod($job, 'buildConfirmationText');
        $method->setAccessible(true);

        return $method->invoke($job, $reservation->fresh(), $locale);
    }

    public function test_paid_reservation_invoice_email_body_contains_merchant_transaction_id(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 12:00:00', 'Europe/Podgorica'));

        $reservation = $this->makePaidReservation([
            'merchant_transaction_id' => 'mt-paid-ref-abc123',
            'preferred_locale' => 'en',
        ]);

        $body = $this->invokePaidInvoiceBody($reservation, 'en');

        $this->assertStringContainsString('Transaction reference: mt-paid-ref-abc123', $body);
        $this->assertStringContainsString('Best regards,', $body);
        $this->assertLessThan(
            strpos($body, 'Best regards,'),
            strpos($body, 'Transaction reference: mt-paid-ref-abc123'),
        );

        Carbon::setTestNow();
    }

    public function test_daily_fee_paid_email_body_contains_merchant_transaction_id(): void
    {
        $reservation = $this->makeDailyTicketReservation([
            'merchant_transaction_id' => 'mt-daily-ref-xyz789',
            'preferred_locale' => 'cg',
        ]);

        $body = $this->invokePaidInvoiceBody($reservation, 'cg');

        $this->assertStringContainsString('Referenca transakcije: mt-daily-ref-xyz789', $body);
    }

    public function test_free_reservation_confirmation_without_mtid_contains_reservation_id_fallback(): void
    {
        $reservation = $this->makePaidReservation([
            'merchant_transaction_id' => null,
            'status' => 'free',
            'invoice_amount' => '0.00',
            'preferred_locale' => 'en',
        ]);

        $body = $this->invokeFreeReservationBody($reservation, 'en');

        $this->assertStringContainsString('Reservation number: '.$reservation->id, $body);
        $this->assertStringNotContainsString('Transaction reference:', $body);
        $this->assertStringNotContainsString('Referenca transakcije:', $body);
    }

    public function test_fulfilled_request_email_lists_reference_for_each_attached_reservation(): void
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 0]);

        $r1 = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-fzbr-one',
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'user_name' => 'School Bus 1',
            'country' => 'ME',
            'license_plate' => 'KO111',
            'vehicle_type_id' => $vt->id,
            'email' => 'school@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $r2 = Reservation::query()->create([
            'merchant_transaction_id' => null,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'user_name' => 'School Bus 2',
            'country' => 'ME',
            'license_plate' => 'KO222',
            'vehicle_type_id' => $vt->id,
            'email' => 'school@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $block = ReservationEmailReferenceLine::forReservations([$r1, $r2], 'en');

        $this->assertStringContainsString('Transaction reference: mt-fzbr-one', $block);
        $this->assertStringContainsString('Reservation number: '.$r2->id, $block);

        Mail::fake();

        $service = app(FreeReservationRequestFulfillmentService::class);
        $method = new ReflectionMethod($service, 'sendMultiConfirmationEmail');
        $method->setAccessible(true);

        $req = new \App\Models\FreeReservationRequest([
            'institution_email' => 'school@example.com',
            'locale' => 'en',
            'reservation_date' => now()->addDays(2),
        ]);
        $req->id = 99;

        $method->invoke($service, $req, [$r1, $r2], forceResend: true);

        Mail::assertSent(FreeReservationRequestFulfilledMail::class, function (FreeReservationRequestFulfilledMail $mail): bool {
            return str_contains($mail->bodyText, 'Transaction reference: mt-fzbr-one')
                && str_contains($mail->bodyText, 'Reservation number:');
        });
    }

    public function test_advance_topup_confirmation_email_contains_top_up_merchant_transaction_id(): void
    {
        $agency = User::factory()->create(['lang' => 'en', 'email_verified_at' => now()]);
        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $agency->id,
            'merchant_transaction_id' => 'mt-advance-ref-555',
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PAID,
        ]);

        $html = view('emails.advance-topup-confirmation', [
            'agency' => $agency,
            'topup' => $topup,
        ])->render();

        $this->assertStringContainsString('Transaction reference: mt-advance-ref-555', $html);
    }

    public function test_no_email_renders_empty_transaction_reference_line(): void
    {
        $this->assertNull(ReservationEmailReferenceLine::forMerchantTransactionId(null, 'cg'));
        $this->assertNull(ReservationEmailReferenceLine::forMerchantTransactionId('   ', 'en'));

        $reservation = $this->makePaidReservation([
            'merchant_transaction_id' => null,
            'status' => 'free',
            'invoice_amount' => '0.00',
            'preferred_locale' => 'cg',
        ]);

        $body = $this->invokeFreeReservationBody($reservation, 'cg');

        $this->assertStringNotContainsString("Referenca transakcije:\n", $body);
        $this->assertStringNotContainsString('Referenca transakcije: ', str_replace('Broj rezervacije: '.$reservation->id, '', $body));
        $this->assertStringContainsString('Broj rezervacije: '.$reservation->id, $body);
    }

    public function test_admin_updated_reservation_email_includes_reference(): void
    {
        $reservation = $this->makePaidReservation([
            'merchant_transaction_id' => 'mt-admin-upd-ref',
            'preferred_locale' => 'en',
        ]);

        $job = new SendAdminUpdatedReservationDocumentJob($reservation->id);
        $method = new ReflectionMethod($job, 'buildBody');
        $method->setAccessible(true);
        $body = $method->invoke($job, $reservation->fresh(), 'en');

        $this->assertStringContainsString('Transaction reference: mt-admin-upd-ref', $body);
    }
}

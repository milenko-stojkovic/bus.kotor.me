<?php

namespace Tests\Feature\Payment;

use App\Jobs\ProcessReservationAfterPaymentJob;
use App\Models\AdminAlert;
use App\Models\ListOfTimeSlot;
use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminFiscalizationAlertService;
use App\Services\AdminPanel\PostFiscalizationAdminAlertService;
use App\Services\FiscalizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PostFiscalizationInfoAdminAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fiscal_failure_creates_post_fiscalization_data_row(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-row');
        $this->mockFiscalFailure('provider_down', notifyAdmin: true);

        (new ProcessReservationAfterPaymentJob($reservation->id))->handle();

        $post = PostFiscalizationData::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($post);
        $this->assertNull($post->resolved_at);
        $this->assertSame('Fiscal provider unavailable', $post->error);
    }

    public function test_fiscal_failure_creates_immediate_info_admin_alert(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-alert');
        $this->mockFiscalFailure('provider_down', notifyAdmin: true);

        (new ProcessReservationAfterPaymentJob($reservation->id))->handle();

        $alert = AdminAlert::query()
            ->where('type', PostFiscalizationAdminAlertService::TYPE)
            ->where('reservation_id', $reservation->id)
            ->first();

        $this->assertNotNull($alert);
        $this->assertSame(AdminAlert::STATUS_UNREAD, $alert->status);
        $this->assertSame('info', $alert->payload_json['severity'] ?? null);
        $this->assertSame(
            'post_fiscalization_started:'.$reservation->id,
            $alert->payload_json['dedupe_key'] ?? null,
        );
        $this->assertSame($reservation->merchant_transaction_id, $alert->merchant_transaction_id);
        $this->assertSame('agency@test.me', $alert->payload_json['email'] ?? null);
        $this->assertSame('2026-07-15', $alert->payload_json['reservation_date'] ?? null);
        $this->assertSame('12.50', (string) ($alert->payload_json['amount'] ?? ''));
        $this->assertSame('provider_down', $alert->payload_json['resolution_reason'] ?? null);
    }

    public function test_info_alert_message_explains_auto_retry_for_24_hours(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-copy');
        $this->mockFiscalFailure('provider_down');

        (new ProcessReservationAfterPaymentJob($reservation->id))->handle();

        $alert = AdminAlert::query()
            ->where('type', PostFiscalizationAdminAlertService::TYPE)
            ->first();

        $this->assertNotNull($alert);
        $this->assertStringContainsString(
            'Rezervacija je ušla u naknadnu fiskalizaciju jer fiskalni servis trenutno nije bio dostupan.',
            $alert->message,
        );
        $this->assertStringContainsString(
            'Sistem će narednih 24 sata automatski pokušavati fiskalizaciju.',
            $alert->message,
        );
        $this->assertStringContainsString('fiscal_error: Fiscal provider unavailable', $alert->message);
    }

    public function test_existing_initial_failure_email_notification_is_still_sent(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-email');
        $this->mockFiscalFailure('provider_down', notifyAdmin: true);

        $fiscalAlerts = Mockery::mock(AdminFiscalizationAlertService::class)->makePartial();
        $fiscalAlerts->shouldReceive('notify')
            ->once()
            ->with(
                Mockery::on(fn (string $subject) => str_contains($subject, 'FISCAL ALERT: initial failure (provider_down)')),
                Mockery::type('string'),
                Mockery::on(fn (array $ctx) => ($ctx['reservation_id'] ?? null) === $reservation->id),
            );
        $this->app->instance(AdminFiscalizationAlertService::class, $fiscalAlerts);

        (new ProcessReservationAfterPaymentJob($reservation->id))->handle();
    }

    public function test_repeated_fiscal_retries_do_not_create_duplicate_info_alerts(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-dedupe');
        $this->mockFiscalFailure('provider_down');

        $job = new ProcessReservationAfterPaymentJob($reservation->id);
        $job->handle();
        $job->handle();

        $this->assertSame(
            1,
            AdminAlert::query()->where('type', PostFiscalizationAdminAlertService::TYPE)->count(),
        );
        $this->assertSame(2, (int) PostFiscalizationData::query()->where('reservation_id', $reservation->id)->value('attempts'));
    }

    public function test_successful_post_fiscalization_resolves_info_alert(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-resolve');
        $this->mockFiscalFailure('provider_down');

        (new ProcessReservationAfterPaymentJob($reservation->id))->handle();

        $post = PostFiscalizationData::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($post);

        $post->applyFiscalDataAndDelete([
            'fiscal_jir' => 'JIR-123',
            'fiscal_ikof' => 'IKOF-456',
            'fiscal_date' => now(),
        ]);

        $alert = AdminAlert::query()
            ->where('type', PostFiscalizationAdminAlertService::TYPE)
            ->where('reservation_id', $reservation->id)
            ->first();

        $this->assertNotNull($alert);
        $this->assertSame(AdminAlert::STATUS_DONE, $alert->status);
        $this->assertNotNull($alert->resolved_at);
        $this->assertNull(PostFiscalizationData::query()->where('reservation_id', $reservation->id)->first());
        $this->assertSame('JIR-123', $reservation->fresh()->fiscal_jir);
    }

    public function test_job_failed_marker_also_creates_info_alert_once(): void
    {
        $reservation = $this->createPaidReservation('tx-post-fiscal-job-fail');

        (new ProcessReservationAfterPaymentJob($reservation->id))->failed(new RuntimeException('simulated'));

        $this->assertSame(
            1,
            AdminAlert::query()->where('type', PostFiscalizationAdminAlertService::TYPE)->count(),
        );

        (new ProcessReservationAfterPaymentJob($reservation->id))->failed(new RuntimeException('again'));

        $this->assertSame(
            1,
            AdminAlert::query()->where('type', PostFiscalizationAdminAlertService::TYPE)->count(),
        );
    }

    public function test_unresolved_post_fiscalization_over_24h_still_sends_escalation_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 12:00:00', 'Europe/Podgorica'));

        $reservation = $this->createPaidReservation('tx-post-fiscal-escalation');

        DB::table('post_fiscalization_data')->insert([
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'error' => 'still down',
            'attempts' => 3,
            'next_retry_at' => now()->subHour(),
            'resolved_at' => null,
            'admin_notified_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->mock(FiscalizationService::class, function ($mock): void {
            $mock->shouldReceive('tryFiscalize')
                ->once()
                ->andReturn([
                    'error' => 'Fiscal provider unavailable',
                    'resolution_reason' => 'provider_down',
                    'retryable' => true,
                ]);
        });

        $fiscalAlerts = Mockery::mock(AdminFiscalizationAlertService::class)->makePartial();
        $fiscalAlerts->shouldReceive('notify')
            ->once()
            ->with(
                Mockery::on(fn (string $subject) => str_contains($subject, 'FISCAL ALERT: retry failing > 1 day')),
                Mockery::type('string'),
                Mockery::on(fn (array $ctx) => ($ctx['reservation_id'] ?? null) === $reservation->id),
            );
        $this->app->instance(AdminFiscalizationAlertService::class, $fiscalAlerts);

        $this->artisan('post-fiscalization:retry')->assertSuccessful();
    }

    private function createPaidReservation(string $merchantTx): Reservation
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 12.5]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Car',
                'description' => null,
            ]);
        }

        return Reservation::query()->create([
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => '2026-07-15',
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => 'KO 123',
            'vehicle_type_id' => $vt->id,
            'email' => 'agency@test.me',
            'merchant_transaction_id' => $merchantTx,
            'status' => 'paid',
            'invoice_amount' => '12.50',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
    }

    private function mockFiscalFailure(string $resolutionReason, bool $notifyAdmin = false): void
    {
        $this->mock(FiscalizationService::class, function ($mock) use ($resolutionReason, $notifyAdmin): void {
            $mock->shouldReceive('tryFiscalize')
                ->andReturn([
                    'error' => 'Fiscal provider unavailable',
                    'resolution_reason' => $resolutionReason,
                    'notify_admin' => $notifyAdmin,
                    'retryable' => true,
                ]);
        });
    }
}

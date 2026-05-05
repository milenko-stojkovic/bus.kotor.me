<?php

namespace Tests\Feature\Limo;

use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Models\Admin;
use App\Models\LimoPickupEvent;
use App\Models\User;
use App\Services\FiscalizationService;
use App\Services\Limo\LimoInvoiceAdapter;
use App\Services\Limo\LimoPickupService;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LimoFiscalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_pickup_job_fills_fiscal_fields(): void
    {
        Mail::fake();

        $this->mock(FiscalizationService::class, function ($mock): void {
            $mock->shouldReceive('tryFiscalizeInvoiceLike')
                ->once()
                ->andReturn([
                    'fiscal_jir' => 'JIR-LIMO-1',
                    'fiscal_ikof' => 'IKOF-LIMO-1',
                    'fiscal_qr' => 'https://example.test/fiscal-qr',
                    'fiscal_operator' => 'Op1',
                    'fiscal_date' => now(),
                ]);
        });

        $event = $this->makeLimoPickupEvent();

        (new ProcessLimoAfterPaymentJob($event->id))->handle();

        $event->refresh();
        $this->assertSame('JIR-LIMO-1', $event->fiscal_jir);
        $this->assertSame('IKOF-LIMO-1', $event->fiscal_ikof);
        $this->assertSame('fiscalized', $event->status);
        $this->assertNotNull($event->invoice_email_sent_at);
    }

    public function test_retry_does_not_duplicate_fiscalization(): void
    {
        Mail::fake();

        $this->mock(FiscalizationService::class, function ($mock): void {
            $mock->shouldReceive('tryFiscalizeInvoiceLike')
                ->once()
                ->andReturn([
                    'fiscal_jir' => 'JIR-LIMO-2',
                    'fiscal_ikof' => 'IKOF-LIMO-2',
                    'fiscal_qr' => 'https://example.test/fiscal-qr-2',
                    'fiscal_operator' => 'Op2',
                    'fiscal_date' => now(),
                ]);
        });

        $event = $this->makeLimoPickupEvent();

        (new ProcessLimoAfterPaymentJob($event->id))->handle();
        (new ProcessLimoAfterPaymentJob($event->id))->handle();

        $event->refresh();
        $this->assertSame('JIR-LIMO-2', $event->fiscal_jir);
    }

    public function test_limo_invoice_email_body_is_cg_when_agency_lang_is_en(): void
    {
        $this->mock(FiscalizationService::class, function ($mock): void {
            $mock->shouldReceive('tryFiscalizeInvoiceLike')
                ->once()
                ->andReturn([
                    'fiscal_jir' => 'JIR-EN-AGENCY',
                    'fiscal_ikof' => 'IKOF-EN',
                    'fiscal_qr' => 'https://example.test/fiscal-qr-en',
                    'fiscal_operator' => 'OpEn',
                    'fiscal_date' => now(),
                ]);
        });

        Mail::shouldReceive('raw')
            ->once()
            ->withArgs(function (string $text, $callback): bool {
                $this->assertStringContainsString('Poštovani,', $text);
                $this->assertStringContainsString('U prilogu je PDF račun za Limo uslugu', $text);
                $this->assertStringContainsString('Hvala.', $text);

                return is_callable($callback);
            });

        app()->setLocale('en');

        $event = $this->makeLimoPickupEvent(['lang' => 'en', 'email' => 'en-agency-invoice@test.local']);

        (new ProcessLimoAfterPaymentJob($event->id))->handle();

        $this->assertNotNull($event->fresh()->invoice_email_sent_at);
    }

    public function test_email_sent_after_success(): void
    {
        Mail::fake();

        $this->mock(FiscalizationService::class, function ($mock): void {
            $mock->shouldReceive('tryFiscalizeInvoiceLike')
                ->once()
                ->andReturn([
                    'fiscal_jir' => 'JIR-LIMO-3',
                    'fiscal_ikof' => 'IKOF-LIMO-3',
                    'fiscal_qr' => 'https://example.test/fiscal-qr-3',
                    'fiscal_operator' => 'Op3',
                    'fiscal_date' => now(),
                ]);
        });

        $event = $this->makeLimoPickupEvent();

        (new ProcessLimoAfterPaymentJob($event->id))->handle();

        $this->assertNotNull($event->fresh()->invoice_email_sent_at);
    }

    public function test_pdf_generated_non_empty(): void
    {
        $event = $this->makeLimoPickupEvent();

        $pdf = app(PaidInvoicePdfGenerator::class)->renderLimoBinary($event, false);
        $this->assertNotSame('', $pdf);
        $this->assertGreaterThan(100, strlen($pdf));
    }

    public function test_invoice_adapter_maps_expected_fields(): void
    {
        $event = $this->makeLimoPickupEvent();
        $o = LimoInvoiceAdapter::fromPickupEvent($event);

        $this->assertSame($event->merchant_transaction_id, $o->merchant_transaction_id);
        $this->assertSame($event->agency_name_snapshot, $o->user_name);
        $this->assertSame($event->agency_email_snapshot, $o->email);
        $this->assertSame((float) $event->amount_snapshot, $o->invoice_amount);
        $this->assertSame(LimoPickupService::SERVICE_NAME, $o->vehicleLine);
    }

    /**
     * @param  array{lang?: string, email?: string, name?: string}  $userOverrides
     */
    private function makeLimoPickupEvent(array $userOverrides = []): LimoPickupEvent
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 14:00:00', 'Europe/Podgorica'));

        $admin = Admin::query()->create([
            'username' => 'limo_fiscal_admin',
            'email' => 'limo-fiscal@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $email = $userOverrides['email'] ?? 'agency-invoice@test.local';
        $user = User::factory()->create([
            'name' => $userOverrides['name'] ?? 'Agency Co',
            'email' => $email,
            'country' => 'ME',
            'lang' => $userOverrides['lang'] ?? 'cg',
        ]);

        return LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => $userOverrides['name'] ?? 'Agency Co',
            'agency_email_snapshot' => $email,
            'agency_country_snapshot' => 'ME',
            'source' => 'qr',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO123AB',
            'amount_snapshot' => '15.00',
            'service_name_snapshot' => LimoPickupService::SERVICE_NAME,
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $admin->id,
            'status' => 'pending_fiscal',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

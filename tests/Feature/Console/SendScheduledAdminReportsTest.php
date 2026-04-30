<?php

namespace Tests\Feature\Console;

use App\Mail\ScheduledAdminReportsMail;
use App\Models\AdminAlert;
use App\Models\ListOfTimeSlot;
use App\Models\ReportEmail;
use App\Models\Reservation;
use App\Models\ScheduledReportDelivery;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminPanel\Reports\AdminReportsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendScheduledAdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedSlotsAndTypes(): array
    {
        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'VT1',
            'description' => 'd1',
        ]);

        return [$slot, $vt];
    }

    private function setNow(string $iso, string $tz = 'Europe/Podgorica'): void
    {
        Carbon::setTestNow(Carbon::parse($iso, $tz));
        date_default_timezone_set($tz);
    }

    public function test_a_daily_sends_previous_day_and_creates_delivery_and_attaches_three_when_advance_on(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', true);

        ReportEmail::query()->create(['email' => 'a@example.com']);

        Mail::fake();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, function (ScheduledAdminReportsMail $m): bool {
            $names = $m->attachmentNames();
            sort($names);

            return $m->hasTo('a@example.com')
                && str_contains($m->subjectLine, 'Dnevni izvještaji')
                && count($names) === 3
                && in_array('dnevni-po-uplati-2026-05-01.pdf', $names, true)
                && in_array('dnevni-obaveze-po-avansu-2026-05-01.pdf', $names, true)
                && in_array('dnevni-po-tipu-vozila-2026-05-01.pdf', $names, true);
        });

        $this->assertDatabaseHas('scheduled_report_deliveries', [
            'period_type' => 'daily',
            'recipient_email' => 'a@example.com',
            'status' => ScheduledReportDelivery::STATUS_SENT,
        ]);
        $row = ScheduledReportDelivery::query()->where('period_type', 'daily')->where('recipient_email', 'a@example.com')->firstOrFail();
        $this->assertSame('2026-05-01', $row->period_start->toDateString());
        $this->assertSame('2026-05-01', $row->period_end->toDateString());
    }

    public function test_b_monthly_previous_month_and_filename_prefix_mjesecni(): void
    {
        $this->setNow('2026-06-01 08:00:00');
        Config::set('features.advance_payments', true);

        ReportEmail::query()->create(['email' => 'a@example.com']);
        Mail::fake();

        $this->artisan('reports:send-scheduled monthly')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, function (ScheduledAdminReportsMail $m): bool {
            $names = $m->attachmentNames();
            return $m->hasTo('a@example.com')
                && str_contains($m->subjectLine, 'Mjesečni izvještaji')
                && in_array('mjesečni-po-uplati-2026-05.pdf', $names, true)
                && in_array('mjesečni-obaveze-po-avansu-2026-05.pdf', $names, true)
                && in_array('mjesečni-po-tipu-vozila-2026-05.pdf', $names, true);
        });

        $this->assertDatabaseHas('scheduled_report_deliveries', [
            'period_type' => 'monthly',
            'recipient_email' => 'a@example.com',
            'status' => ScheduledReportDelivery::STATUS_SENT,
        ]);
        $row = ScheduledReportDelivery::query()->where('period_type', 'monthly')->where('recipient_email', 'a@example.com')->firstOrFail();
        $this->assertSame('2026-05-01', $row->period_start->toDateString());
        $this->assertSame('2026-05-31', $row->period_end->toDateString());
    }

    public function test_c_yearly_previous_year_and_filename_prefix_godisnji(): void
    {
        $this->setNow('2027-01-01 08:00:00');
        Config::set('features.advance_payments', true);

        ReportEmail::query()->create(['email' => 'a@example.com']);
        Mail::fake();

        $this->artisan('reports:send-scheduled yearly')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, function (ScheduledAdminReportsMail $m): bool {
            $names = $m->attachmentNames();
            return $m->hasTo('a@example.com')
                && str_contains($m->subjectLine, 'Godišnji izvještaji')
                && in_array('godišnji-po-uplati-2026.pdf', $names, true)
                && in_array('godišnji-obaveze-po-avansu-2026.pdf', $names, true)
                && in_array('godišnji-po-tipu-vozila-2026.pdf', $names, true);
        });

        $this->assertDatabaseHas('scheduled_report_deliveries', [
            'period_type' => 'yearly',
            'recipient_email' => 'a@example.com',
            'status' => ScheduledReportDelivery::STATUS_SENT,
        ]);
        $row = ScheduledReportDelivery::query()->where('period_type', 'yearly')->where('recipient_email', 'a@example.com')->firstOrFail();
        $this->assertSame('2026-01-01', $row->period_start->toDateString());
        $this->assertSame('2026-12-31', $row->period_end->toDateString());
    }

    public function test_d_multiple_recipients_send_separate_emails(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', true);

        ReportEmail::query()->create(['email' => 'a@example.com']);
        ReportEmail::query()->create(['email' => 'b@example.com']);
        Mail::fake();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, 2);
        $this->assertDatabaseCount('scheduled_report_deliveries', 2);
    }

    public function test_e_no_recipients_skips_without_sending(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', true);
        Mail::fake();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('scheduled_report_deliveries', 0);
    }

    public function test_f_idempotency_does_not_resend_same_period_to_same_recipient(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', true);
        ReportEmail::query()->create(['email' => 'a@example.com']);
        Mail::fake();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);
        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, 1);
        $this->assertDatabaseCount('scheduled_report_deliveries', 1);
    }

    public function test_g_advance_feature_off_skips_advance_pdf_and_still_sends_other_two(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', false);
        ReportEmail::query()->create(['email' => 'a@example.com']);
        Mail::fake();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(0);

        Mail::assertSent(ScheduledAdminReportsMail::class, function (ScheduledAdminReportsMail $m): bool {
            $names = $m->attachmentNames();
            return $m->hasTo('a@example.com')
                && count($names) === 2
                && in_array('dnevni-po-uplati-2026-05-01.pdf', $names, true)
                && in_array('dnevni-po-tipu-vozila-2026-05-01.pdf', $names, true);
        });
    }

    public function test_h_delivery_failure_creates_admin_alert_and_sends_admin_email(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        Config::set('features.advance_payments', true);
        ReportEmail::query()->create(['email' => 'a@example.com']);

        // First recipient send throws, so command should fail and create alert + admin email.
        Mail::fake();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));
        Mail::shouldReceive('raw')->once();

        $this->artisan('reports:send-scheduled daily')->assertExitCode(1);

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'scheduled_admin_reports_failed',
        ]);
        $this->assertGreaterThanOrEqual(1, AdminAlert::query()->where('type', 'scheduled_admin_reports_failed')->count());
    }

    public function test_i_by_payment_includes_paid_reservation_created_at_1530_for_daily_previous_day(): void
    {
        $this->setNow('2026-05-02 08:00:00');
        [$slot, $vt] = $this->seedSlotsAndTypes();

        $createdAt = Carbon::parse('2026-05-01 15:30:00', 'Europe/Podgorica');
        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-sched-pay-daily-1',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-05-10',
            'user_name' => 'P',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'p@example.com',
            'status' => 'paid',
            'invoice_amount' => '12.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $r->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $from = Carbon::now('Europe/Podgorica')->subDay()->startOfDay();
        $to = $from->copy(); // command uses the same date; byPayment uses whereDate

        $data = app(AdminReportsService::class)->byPayment($from, $to);
        $this->assertSame(1, $data['transactions']);
        $this->assertSame(12.0, $data['revenue_eur']);
    }

    public function test_j_by_payment_includes_paid_reservation_created_at_1530_on_last_day_of_previous_month(): void
    {
        // On June 1, scheduled monthly uses previous month (May 1..May 31).
        $this->setNow('2026-06-01 08:00:00');
        [$slot, $vt] = $this->seedSlotsAndTypes();

        $createdAt = Carbon::parse('2026-05-31 15:30:00', 'Europe/Podgorica');
        $r = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-sched-pay-monthly-1',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-06-10',
            'user_name' => 'P',
            'country' => 'ME',
            'license_plate' => 'KO2',
            'vehicle_type_id' => $vt->id,
            'email' => 'p@example.com',
            'status' => 'paid',
            'invoice_amount' => '20.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $r->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $from = Carbon::now('Europe/Podgorica')->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $to = $from->copy()->endOfMonth()->startOfDay(); // command uses startOfDay; byPayment uses whereDate

        $data = app(AdminReportsService::class)->byPayment($from, $to);
        $this->assertSame(1, $data['transactions']);
        $this->assertSame(20.0, $data['revenue_eur']);
    }
}


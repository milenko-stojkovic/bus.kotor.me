<?php

namespace Tests\Feature\Panel;

use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\Pdf\PanelStatisticsPdfGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelStatisticsPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_export_uses_same_filtered_dataset_and_only_current_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20 12:00:00', 'Europe/Podgorica'));

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'VT1',
            'description' => 'd1',
        ]);

        $u1 = User::query()->create([
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => bcrypt('secret'),
            'country' => 'ME',
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);
        $u2 = User::query()->create([
            'name' => 'B',
            'email' => 'b@example.com',
            'password' => bcrypt('secret'),
            'country' => 'ME',
            'lang' => 'cg',
            'email_verified_at' => now(),
        ]);

        // u1: inside interval
        Reservation::query()->create([
            'user_id' => $u1->id,
            'merchant_transaction_id' => 'mt-stat-pdf-1',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-04-15',
            'user_name' => 'A',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'a@example.com',
            'status' => 'paid',
            'invoice_amount' => '20.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // u1: outside interval
        Reservation::query()->create([
            'user_id' => $u1->id,
            'merchant_transaction_id' => 'mt-stat-pdf-2',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-04-10',
            'user_name' => 'A',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'a@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // another user, same date (must be excluded)
        Reservation::query()->create([
            'user_id' => $u2->id,
            'merchant_transaction_id' => 'mt-stat-pdf-other',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-04-15',
            'user_name' => 'B',
            'country' => 'ME',
            'license_plate' => 'KO9',
            'vehicle_type_id' => $vt->id,
            'email' => 'b@example.com',
            'status' => 'paid',
            'invoice_amount' => '999.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $captured = null;
        $capturedLocale = null;
        $this->app->bind(PanelStatisticsPdfGenerator::class, function () use (&$captured, &$capturedLocale) {
            return new class($captured, $capturedLocale) extends PanelStatisticsPdfGenerator {
                public function __construct(private mixed &$captured, private mixed &$capturedLocale) {}

                public function renderBinary(array $dataset, string $locale = 'cg'): string
                {
                    $this->captured = $dataset;
                    $this->capturedLocale = $locale;

                    return '%PDF-stub';
                }
            };
        });

        $this->actingAs($u1);

        $html = $this->get(route('panel.statistics', [
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-15',
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('20.00', $html);
        $this->assertStringNotContainsString('999.00', $html);

        $pdf = $this->get(route('panel.statistics.pdf', [
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-15',
        ], false));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $this->assertIsArray($captured);
        $this->assertSame('en', $capturedLocale);
        $this->assertSame('2026-04-15', $captured['date_from']);
        $this->assertSame('2026-04-15', $captured['date_to']);
        $this->assertSame(20.0, (float) $captured['total_paid']);
        $this->assertSame(1, (int) $captured['visit_count']);
        $this->assertSame('A', $captured['agency_name']);
    }

    public function test_pdf_export_default_range_matches_ui_defaults(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20 12:00:00', 'Europe/Podgorica'));

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'VT1',
            'description' => 'd1',
        ]);

        $u1 = User::query()->create([
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => bcrypt('secret'),
            'country' => 'ME',
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        // Ensures min bound is 2026-04-10 (past => realized).
        Reservation::query()->create([
            'user_id' => $u1->id,
            'merchant_transaction_id' => 'mt-stat-pdf-min',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-04-10',
            'user_name' => 'A',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'a@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $captured = null;
        $capturedLocale = null;
        $this->app->bind(PanelStatisticsPdfGenerator::class, function () use (&$captured, &$capturedLocale) {
            return new class($captured, $capturedLocale) extends PanelStatisticsPdfGenerator {
                public function __construct(private mixed &$captured, private mixed &$capturedLocale) {}

                public function renderBinary(array $dataset, string $locale = 'cg'): string
                {
                    // Simulate what real generator does: temporarily change app locale during render.
                    $prev = app()->getLocale();
                    app()->setLocale($locale);

                    try {
                        $this->captured = $dataset;
                        $this->capturedLocale = $locale;
                    } finally {
                        app()->setLocale($prev);
                    }

                    return '%PDF-stub';
                }
            };
        });

        $this->actingAs($u1);

        $this->get(route('panel.statistics', [], false))->assertOk();
        $this->get(route('panel.statistics.pdf', [], false))->assertOk();

        $this->assertIsArray($captured);
        $this->assertSame('en', $capturedLocale);
        $this->assertSame('2026-04-10', $captured['date_from']);
        $this->assertSame('2026-07-19', $captured['date_to']); // today(2026-04-20) + 90 days
    }

    public function test_statistics_page_pdf_link_preserves_query_params(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20 12:00:00', 'Europe/Podgorica'));

        $u1 = User::query()->create([
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => bcrypt('secret'),
            'country' => 'ME',
            'lang' => 'cg',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($u1);

        $resp = $this->get(route('panel.statistics', [
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-16',
        ], false))->assertOk();

        $url = route('panel.statistics.pdf', [
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-16',
        ], false);

        // Blade escapes "&" in query string into "&amp;".
        $resp->assertSee(htmlspecialchars($url, ENT_QUOTES), false);
    }
}


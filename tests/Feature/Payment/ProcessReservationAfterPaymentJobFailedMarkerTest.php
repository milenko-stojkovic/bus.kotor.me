<?php

namespace Tests\Feature\Payment;

use App\Jobs\ProcessReservationAfterPaymentJob;
use App\Models\ListOfTimeSlot;
use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\Payment\PaymentResultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessReservationAfterPaymentJobFailedMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_creates_unresolved_post_row_when_no_jir_and_no_existing_post(): void
    {
        [$r] = $this->createPaidReservationWithoutFiscal('tx-job-fail-marker');

        (new ProcessReservationAfterPaymentJob($r->id))->failed(new RuntimeException('simulated'));

        $post = PostFiscalizationData::query()->where('reservation_id', $r->id)->first();
        $this->assertNotNull($post);
        $this->assertNull($post->resolved_at);
        $this->assertStringContainsString(
            ProcessReservationAfterPaymentJob::RESOLUTION_JOB_FAILED_BEFORE_FISCAL,
            (string) $post->error
        );
        $this->assertNotNull($post->next_retry_at);

        $resolved = app(PaymentResultResolver::class)->resolve('tx-job-fail-marker');
        $this->assertNotNull($resolved);
        $this->assertTrue($resolved['fiscal_delayed_known']);
    }

    public function test_failed_does_not_create_second_row_when_unresolved_post_exists(): void
    {
        [$r] = $this->createPaidReservationWithoutFiscal('tx-job-fail-dup');

        PostFiscalizationData::query()->create([
            'reservation_id' => $r->id,
            'merchant_transaction_id' => $r->merchant_transaction_id,
            'error' => 'fiscal api down',
            'attempts' => 1,
            'next_retry_at' => now()->addHour(),
        ]);

        (new ProcessReservationAfterPaymentJob($r->id))->failed(new RuntimeException('after partial save'));

        $this->assertSame(1, PostFiscalizationData::query()->where('reservation_id', $r->id)->count());
    }

    public function test_failed_skips_when_reservation_is_free(): void
    {
        [$r] = $this->createPaidReservationWithoutFiscal('tx-free-skip');
        $r->update(['status' => 'free']);

        (new ProcessReservationAfterPaymentJob($r->id))->failed(new RuntimeException('x'));

        $this->assertNull(PostFiscalizationData::query()->where('reservation_id', $r->id)->first());
    }

    /**
     * @return array{0: Reservation}
     */
    private function createPaidReservationWithoutFiscal(string $merchantTx): array
    {
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

        $r = Reservation::query()->create([
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => '2026-07-01',
            'user_name' => 'X',
            'country' => 'ME',
            'license_plate' => 'AA 1',
            'vehicle_type_id' => $vt->id,
            'email' => 'x@test.me',
            'merchant_transaction_id' => $merchantTx,
            'status' => 'paid',
            'invoice_amount' => 5,
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        return [$r];
    }
}

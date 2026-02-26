<?php

namespace App\Jobs;

use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use App\Services\FiscalizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Payment states: pending → success → [fiscalization_failed → post_fiscalization_data].
 *
 * Reservations are created on success (even if fiscalization fails). Failed fiscalization must not
 * block invoice generation: we always dispatch PDF + email (fiscal or non-fiscal).
 *
 * Try fiscalization. On SUCCESS: update reservation fiscal_*, generate fiscal PDF, send invoice.
 * On FAILURE (fiscalization_failed): insert post_fiscalization_data, generate non-fiscal PDF + email.
 * Do NOT rollback reservation.
 */
class ProcessReservationAfterPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry: delimično uspeo (npr. PDF kreiran, mail nije) – idempotentni koraci preskoče duplikat. */
    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public int $reservationId
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (! $reservation) {
            return;
        }

        if ($reservation->fiscal_jir !== null) {
            return;
        }

        $result = app(FiscalizationService::class)->tryFiscalize($reservation);

        if (isset($result['fiscal_jir'])) {
            $reservation->update([
                'fiscal_jir' => $result['fiscal_jir'],
                'fiscal_ikof' => $result['fiscal_ikof'],
                'fiscal_qr' => $result['fiscal_qr'] ?? null,
                'fiscal_operator' => $result['fiscal_operator'] ?? null,
                'fiscal_date' => $result['fiscal_date'] ?? now(),
            ]);
            $this->dispatchPdfAndEmail($reservation->id, true);
            return;
        }

        $errorMessage = $result['error'] ?? 'Fiscal service unavailable';
        PostFiscalizationData::create([
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'error' => $errorMessage,
            'attempts' => 1,
            'next_retry_at' => now()->addMinutes(15),
        ]);
        $this->dispatchPdfAndEmail($reservation->id, false);
    }

    /** PDF then email (chain so PDF exists when email sends). */
    private function dispatchPdfAndEmail(int $reservationId, bool $isFiscal): void
    {
        GenerateInvoicePdfJob::withChain([
            new SendInvoiceEmailJob($reservationId, $isFiscal),
        ])->dispatch($reservationId, $isFiscal);
    }

}

<?php

namespace App\Jobs;

use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use App\Services\AdminFiscalizationAlertService;
use App\Services\FiscalizationService;
use Illuminate\Support\Facades\Log;
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

        $resolutionReason = is_string($result['resolution_reason'] ?? null) ? (string) $result['resolution_reason'] : 'unknown_fiscal_error';
        $notifyAdmin = (bool) ($result['notify_admin'] ?? false);
        $retryable = (bool) ($result['retryable'] ?? true);

        // Rule: already_fiscalized (78) means provider says it's already fiscalized.
        // If we can find ANY reservation for this merchant_transaction_id with fiscal_jir set → ignore (already fiscalized).
        // If not found → notify admin with all available reservation data and do NOT enqueue retries.
        if ($resolutionReason === 'already_fiscalized') {
            $existingFiscal = Reservation::query()
                ->where('merchant_transaction_id', $reservation->merchant_transaction_id)
                ->whereNotNull('fiscal_jir')
                ->first();

            if ($existingFiscal) {
                Log::channel('payments')->warning('Fiscal provider returned already_fiscalized; ignoring because fiscalized reservation exists', [
                    'reservation_id' => $reservation->id,
                    'merchant_transaction_id' => $reservation->merchant_transaction_id,
                    'existing_reservation_id' => $existingFiscal->id,
                ]);
                return;
            }

            $alerts = app(AdminFiscalizationAlertService::class);
            $alerts->notify(
                'FISCAL ALERT: already_fiscalized but not found in DB',
                "Provider returned already_fiscalized (78), but no reservation with fiscal_jir was found for this transaction.\n\n"
                ."resolution_reason: ".$resolutionReason."\n"
                ."error: ".$errorMessage."\n\n"
                .$alerts->buildReservationContext($reservation)."\n"
            , [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'resolution_reason' => $resolutionReason,
            ]);

            // Audit row (resolved immediately; no retries)
            PostFiscalizationData::create([
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'error' => $errorMessage,
                'attempts' => 1,
                'next_retry_at' => null,
                'resolved_at' => now(),
                'admin_notified_at' => now(),
            ]);

            return;
        }

        // Insert/update one unresolved row per reservation for retry pipeline.
        $post = PostFiscalizationData::query()
            ->where('reservation_id', $reservation->id)
            ->unresolved()
            ->first();

        if (! $post) {
            $post = new PostFiscalizationData();
            $post->reservation_id = $reservation->id;
            $post->merchant_transaction_id = $reservation->merchant_transaction_id;
            $post->attempts = 1;
        } else {
            $post->attempts = (int) ($post->attempts ?? 0) + 1;
        }

        $post->error = is_string($errorMessage) ? $errorMessage : 'Fiscal service unavailable';
        $post->next_retry_at = $retryable
            ? now()->addMinutes(15 * max(1, (int) $post->attempts))
            : null;

        // Immediate admin rules:
        // - deposit_missing: notify admin
        // - timeout: do NOT notify admin immediately
        // - all other fiscal errors: notify admin
        if ($resolutionReason === 'timeout') {
            $notifyAdmin = false;
        }

        if ($notifyAdmin && $post->admin_notified_at === null) {
            $alerts = app(AdminFiscalizationAlertService::class);
            $alerts->notify(
                'FISCAL ALERT: initial failure ('.$resolutionReason.')',
                "Initial fiscalization failed after successful payment.\n\n"
                ."resolution_reason: ".$resolutionReason."\n"
                ."error: ".$errorMessage."\n\n"
                .$alerts->buildReservationContext($reservation)."\n"
            , [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'resolution_reason' => $resolutionReason,
            ]);
            $post->admin_notified_at = now();
        }

        $post->save();
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

<?php

namespace App\Jobs;

use App\Events\PaymentFailed;
use App\Models\Reservation;
use App\Models\TempData;
use App\Services\Payment\ErrorClassifier;
use App\Services\Payment\PaymentSuccessHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payment state machine. Only API bank callbacks drive transitions.
 *
 * Rules:
 * - All payments start as pending. processed is terminal (no further transitions).
 * - late_success only when: bank SUCCESS but lock already expired/canceled; never creates reservation.
 * - processed is idempotent: duplicate SUCCESS callbacks must not create duplicate reservations.
 * - Every transition logged; all transitions inside DB transaction. Never rely on frontend for state.
 */
class PaymentCallbackJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @return array<int, int> seconds before retry 2 and 3
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public array $payload,
        public array $rawPayload = []
    ) {}

    public function handle(): void
    {
        $txId = $this->payload['merchant_transaction_id'] ?? null;
        $callbackStatus = $this->normalizeStatus($this->payload['status'] ?? null);

        if (! $txId || ! $callbackStatus) {
            Log::channel('payments')->warning('Payment callback job skipped: missing tx or unknown status', [
                'merchant_transaction_id' => $txId,
                'status' => $this->payload['status'] ?? null,
            ]);
            return;
        }

        $temp = TempData::where('merchant_transaction_id', $txId)->first();
        if (! $temp) {
            Log::channel('payments')->warning('Payment callback job skipped: temp_data not found', [
                'merchant_transaction_id' => $txId,
                'status' => $callbackStatus,
            ]);
            return;
        }

        if ($temp->isTerminal()) {
            Log::channel('payments')->info('Payment callback job terminal temp_data', [
                'merchant_transaction_id' => $txId,
                'status' => $callbackStatus,
                'temp_status' => $temp->status,
            ]);
            if ($temp->status === TempData::STATUS_PROCESSED) {
                return;
            }
            if ($callbackStatus === 'success' && in_array($temp->status, [TempData::STATUS_CANCELED, TempData::STATUS_EXPIRED], true)) {
                app(PaymentSuccessHandler::class)->applyLateSuccess($temp, $this->rawPayload, false);
            }
            return;
        }

        if (Reservation::where('merchant_transaction_id', $txId)->exists()) {
            Log::channel('payments')->info('Payment callback job duplicate: reservation already exists', [
                'merchant_transaction_id' => $txId,
                'status' => $callbackStatus,
            ]);
            return;
        }

        if ($callbackStatus === 'success') {
            app(PaymentSuccessHandler::class)->handle($temp, $this->rawPayload);
            return;
        }

        if ($callbackStatus === 'timeout') {
            app(PaymentSuccessHandler::class)->applyLateSuccess($temp, $this->rawPayload, true);
            return;
        }

        $this->handleCanceled($temp);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }
        if (in_array($status, ['CANCEL', 'ERROR'], true)) {
            return 'failed';
        }

        return in_array($status, ['success', 'failed', 'timeout'], true) ? $status : null;
    }

    private function handleCanceled(TempData $temp): void
    {
        DB::transaction(function () use ($temp): void {
            $temp = TempData::where('merchant_transaction_id', $temp->merchant_transaction_id)->lockForUpdate()->first();
            if (! $temp || $temp->isTerminal()) {
                return;
            }
            $from = $temp->status;

            $rawCode = $this->payload['error_code']
                ?? ($this->payload['code'] ?? ($this->payload['errorCode'] ?? null))
                ?? ($this->rawPayload['code'] ?? ($this->rawPayload['errorCode'] ?? null));

            $rawMessage = $this->payload['error_reason']
                ?? ($this->payload['message'] ?? ($this->payload['errorMessage'] ?? null))
                ?? ($this->rawPayload['message'] ?? ($this->rawPayload['errorMessage'] ?? null));

            $classified = app(ErrorClassifier::class)->classify('bankart', $rawCode, $rawMessage, is_array($this->rawPayload) ? $this->rawPayload : null);

            $temp->update([
                'status' => TempData::STATUS_CANCELED,
                'raw_callback_payload' => $this->rawPayload,
                'callback_error_code' => $rawCode,
                'callback_error_reason' => $rawMessage,
                'resolution_reason' => $classified['resolution_reason'],
            ]);
            TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_CANCELED, 'CANCEL/ERROR/failed');
            app(PaymentSuccessHandler::class)->releaseSoftLock($temp, false);
            event(new PaymentFailed($temp));
        });
    }

    public function uniqueId(): string
    {
        return (string) ($this->payload['merchant_transaction_id'] ?? $this->job?->getJobId() ?? 'payment-callback');
    }

    public function failed(?Throwable $e): void
    {
        Log::channel('payments')->error('payment_callback_job_exhausted', [
            'merchant_transaction_id' => $this->payload['merchant_transaction_id'] ?? null,
            'message' => $e?->getMessage(),
            'exception' => $e !== null ? $e::class : null,
        ]);
    }
}

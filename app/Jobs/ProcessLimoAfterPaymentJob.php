<?php

namespace App\Jobs;

use App\Models\LimoPickupEvent;
use App\Services\FiscalizationService;
use App\Services\Limo\LimoInvoiceAdapter;
use App\Support\QueueMode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fiskalizacija Limo pickup događaja + PDF email (isti servis i šablon kao plaćene rezervacije, preko adaptera).
 */
class ProcessLimoAfterPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [120, 600, 1800];
    }

    public function __construct(
        public readonly int $limoPickupEventId,
    ) {}

    public function handle(): void
    {
        $event = LimoPickupEvent::query()->find($this->limoPickupEventId);
        if ($event === null) {
            return;
        }

        if ($event->fiscal_jir !== null && $event->invoice_email_sent_at !== null) {
            return;
        }

        if ($event->fiscal_jir !== null && $event->invoice_email_sent_at === null) {
            $this->dispatchInvoiceEmail($event->id, true);

            return;
        }

        Log::channel('payments')->info('limo_fiscal_started', [
            'limo_pickup_event_id' => $event->id,
            'merchant_transaction_id' => $event->merchant_transaction_id,
        ]);

        $invoice = LimoInvoiceAdapter::fromPickupEvent($event);
        $result = app(FiscalizationService::class)->tryFiscalizeInvoiceLike($invoice);

        if (isset($result['fiscal_jir'])) {
            $event->update([
                'fiscal_jir' => $result['fiscal_jir'],
                'fiscal_ikof' => $result['fiscal_ikof'],
                'fiscal_qr' => $result['fiscal_qr'] ?? null,
                'fiscal_operator' => $result['fiscal_operator'] ?? (config('services.fiscal.enu_identifier') ?: null),
                'fiscal_date' => $result['fiscal_date'] ?? now(),
                'status' => 'fiscalized',
            ]);

            Log::channel('payments')->info('limo_fiscal_success', [
                'limo_pickup_event_id' => $event->id,
                'merchant_transaction_id' => $event->merchant_transaction_id,
            ]);

            $this->dispatchInvoiceEmail($event->id, true);

            return;
        }

        $errorMessage = $result['error'] ?? 'Fiscal service unavailable';
        Log::channel('payments')->warning('limo_fiscal_failed', [
            'limo_pickup_event_id' => $event->id,
            'merchant_transaction_id' => $event->merchant_transaction_id,
            'error' => is_string($errorMessage) ? $errorMessage : 'unknown',
        ]);

        $event->update([
            'status' => 'fiscal_failed',
        ]);

        $this->dispatchInvoiceEmail($event->id, false);
    }

    private function dispatchInvoiceEmail(int $limoPickupEventId, bool $isFiscal): void
    {
        QueueMode::dispatchForFakeE2e(new SendLimoInvoiceEmailJob($limoPickupEventId, $isFiscal));
    }

    public function failed(?Throwable $e): void
    {
        $mtid = LimoPickupEvent::query()->whereKey($this->limoPickupEventId)->value('merchant_transaction_id');
        Log::channel('payments')->error('process_limo_after_payment_job_exhausted', [
            'limo_pickup_event_id' => $this->limoPickupEventId,
            'merchant_transaction_id' => $mtid,
            'message' => $e?->getMessage(),
            'exception' => $e !== null ? $e::class : null,
        ]);
    }
}

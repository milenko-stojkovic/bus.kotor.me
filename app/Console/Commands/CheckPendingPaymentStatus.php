<?php

namespace App\Console\Commands;

use App\Contracts\PaymentStatusInquiryService;
use App\Models\Reservation;
use App\Models\TempData;
use App\Services\Payment\PaymentSuccessHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cron: (1) temp_data pending predugo → log `payment_pending_too_long` (bez promene statusa).
 * (2) Ako je {@see PaymentStatusInquiryService::isImplemented()} true → status inquiry; SUCCESS → isti flow kao callback.
 */
class CheckPendingPaymentStatus extends Command
{
    protected $signature = 'payment:check-pending-inquiry';

    protected $description = 'Warn on stale pending temp_data; optional bank status inquiry when implemented';

    public function handle(PaymentStatusInquiryService $inquiry, PaymentSuccessHandler $successHandler): int
    {
        $warnAfter = (int) config('payment.stale_pending_warn_after_minutes', 12);
        $warnCutoff = now()->subMinutes(max(1, $warnAfter));

        $staleForWarn = TempData::query()
            ->where('status', TempData::STATUS_PENDING)
            ->where('created_at', '<', $warnCutoff)
            ->get();

        foreach ($staleForWarn as $temp) {
            $cacheKey = 'payment_pending_too_long:'.$temp->id;
            if (! Cache::add($cacheKey, 1, now()->addHours(6))) {
                continue;
            }
            $ageMinutes = (int) $temp->created_at?->diffInMinutes(now());
            $reservationId = Reservation::query()
                ->where('merchant_transaction_id', $temp->merchant_transaction_id)
                ->value('id');

            Log::channel('payments')->warning('payment_pending_too_long', [
                'merchant_transaction_id' => $temp->merchant_transaction_id,
                'temp_data_id' => $temp->id,
                'reservation_id' => $reservationId,
                'age_minutes' => $ageMinutes,
                'status_inquiry_implemented' => $inquiry->isImplemented(),
            ]);
        }

        if (! $inquiry->isImplemented()) {
            $this->info('Status inquiry not implemented; logged '.$staleForWarn->count().' stale pending warning(s) (throttled).');

            return self::SUCCESS;
        }

        $minutes = (int) config('payment.pending_inquiry_after_minutes', 10);
        $inquiryCutoff = now()->subMinutes($minutes);

        $pending = TempData::where('status', TempData::STATUS_PENDING)
            ->where('created_at', '<', $inquiryCutoff)
            ->get();

        $applied = 0;
        foreach ($pending as $temp) {
            $status = $inquiry->inquire($temp->merchant_transaction_id);
            if ($status === 'success') {
                $created = $successHandler->handle($temp, ['source' => 'status_inquiry']);
                if ($created) {
                    $applied++;
                }
            }
        }

        $this->info('Inquiry: checked '.$pending->count().' pending; applied success for '.$applied.'.');

        return self::SUCCESS;
    }
}

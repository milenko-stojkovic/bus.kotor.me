<?php

namespace App\Console\Commands;

use App\Contracts\PaymentStatusInquiryService;
use App\Models\TempData;
use App\Services\Payment\PaymentSuccessHandler;
use Illuminate\Console\Command;

/**
 * Cron: temp_data pending starije od X min → status inquiry kod banke.
 * Ako banka kaže SUCCESS → isti flow kao callback (PaymentSuccessHandler).
 * Timeout callback-a: callback nikad ne stigne (mreža, firewall, outage).
 * V. config payment.pending_inquiry_after_minutes.
 */
class CheckPendingPaymentStatus extends Command
{
    protected $signature = 'payment:check-pending-inquiry';

    protected $description = 'Check pending payments older than threshold via bank status inquiry; apply success flow if bank says SUCCESS';

    public function handle(PaymentStatusInquiryService $inquiry, PaymentSuccessHandler $successHandler): int
    {
        $minutes = (int) config('payment.pending_inquiry_after_minutes', 10);
        $cutoff = now()->subMinutes($minutes);

        $pending = TempData::where('status', TempData::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
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

        $this->info('Checked '.$pending->count().' pending; applied success for '.$applied.'.');

        return self::SUCCESS;
    }
}

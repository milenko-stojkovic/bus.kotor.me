<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\PostFiscalizationData;
use App\Services\AdminFiscalizationAlertService;
use App\Services\FiscalizationService;
use Illuminate\Console\Command;

/**
 * Cron: retry fiskalizacije za rezervacije iz post_fiscalization_data (next_retry_at <= now).
 * Uspeh → ažurira reservation fiscal_*, briše slog, šalje kupcu novi fiskalni PDF i email.
 * Neuspeh → poveća attempts, postavi next_retry_at. V. docs/cron-commands.md.
 */
class RetryPostFiscalization extends Command
{
    protected $signature = 'post-fiscalization:retry';

    protected $description = 'Retry fiscalization for post_fiscalization_data rows; on success send fiscal PDF to customer';

    public function handle(FiscalizationService $fiscalization): int
    {
        $rows = PostFiscalizationData::unresolved()
            ->where('next_retry_at', '<=', now())
            ->with('reservation')
            ->get();

        foreach ($rows as $post) {
            $reservation = $post->reservation;
            if (! $reservation) {
                $post->delete();
                continue;
            }

            if ($reservation->fiscal_jir !== null) {
                $post->delete();
                continue;
            }

            $result = $fiscalization->tryFiscalize($reservation);

            if (isset($result['fiscal_jir'])) {
                $post->applyFiscalDataAndDelete($result);
                GenerateInvoicePdfJob::withChain([
                    new SendInvoiceEmailJob($reservation->id, true),
                ])->dispatch($reservation->id, true);
                $this->info('Fiscalized reservation '.$reservation->id.', sent fiscal PDF.');
                continue;
            }

            $post->increment('attempts');
            $retryable = (bool) ($result['retryable'] ?? true);
            $post->update([
                'error' => $result['error'] ?? 'Fiscal service unavailable',
                'next_retry_at' => $retryable ? now()->addMinutes(15 * $post->attempts) : null,
            ]);

            // Rule: if fiscalization has been failing for > 1 day, notify admin (at most once per day).
            $isOlderThanDay = $post->created_at !== null && $post->created_at->lte(now()->subDay());
            $shouldNotifyNow = $isOlderThanDay && ($post->admin_notified_at === null || $post->admin_notified_at->lte(now()->subDay()));
            if ($shouldNotifyNow) {
                $reason = $result['resolution_reason'] ?? ($result['category'] ?? 'error');
                $alerts = app(AdminFiscalizationAlertService::class);
                $alerts->notify(
                    'FISCAL ALERT: retry failing > 1 day ('.$reason.')',
                    "Fiscalization retry has been failing for more than 1 day.\n\n"
                    ."reason: ".$reason."\n"
                    ."error: ".($result['error'] ?? 'Fiscal service unavailable')."\n\n"
                    .$alerts->buildReservationContext($reservation)."\n\n"
                    .$alerts->buildPostRowContext($post)."\n"
                , [
                    'reservation_id' => $reservation->id,
                    'merchant_transaction_id' => $reservation->merchant_transaction_id,
                    'post_fiscalization_data_id' => $post->id,
                ]);
                $post->update(['admin_notified_at' => now()]);
            }
        }

        // Also notify about "stuck" rows older than 1 day even if next_retry_at is NULL (non-retryable),
        // or if backoff pushed next_retry_at into the future.
        $stale = PostFiscalizationData::unresolved()
            ->where('created_at', '<=', now()->subDay())
            ->where(function ($q) {
                $q->whereNull('admin_notified_at')
                    ->orWhere('admin_notified_at', '<=', now()->subDay());
            })
            ->with('reservation')
            ->get();

        foreach ($stale as $post) {
            $reservation = $post->reservation;
            if (! $reservation) {
                continue;
            }

            $alerts = app(AdminFiscalizationAlertService::class);
            $alerts->notify(
                'FISCAL ALERT: unresolved > 1 day',
                "Fiscalization unresolved for more than 1 day.\n\n"
                .$alerts->buildReservationContext($reservation)."\n\n"
                .$alerts->buildPostRowContext($post)."\n"
            , [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'post_fiscalization_data_id' => $post->id,
            ]);
            $post->update(['admin_notified_at' => now()]);
        }

        $this->info('Processed '.$rows->count().' post_fiscalization_data rows.');
        return self::SUCCESS;
    }
}

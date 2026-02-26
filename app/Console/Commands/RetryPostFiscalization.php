<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\PostFiscalizationData;
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
            $post->update([
                'error' => $result['error'] ?? 'Fiscal service unavailable',
                'next_retry_at' => now()->addMinutes(15 * $post->attempts),
            ]);
        }

        $this->info('Processed '.$rows->count().' post_fiscalization_data rows.');
        return self::SUCCESS;
    }
}

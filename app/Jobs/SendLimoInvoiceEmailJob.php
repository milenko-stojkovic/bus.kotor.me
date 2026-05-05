<?php

namespace App\Jobs;

use App\Models\LimoPickupEvent;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use App\Support\UiText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Email sa PDF računom za Limo pickup (fiskalnim ili nefiskalnim). Idempotentno kao {@see SendInvoiceEmailJob}.
 */
class SendLimoInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function __construct(
        public int $limoPickupEventId,
        public bool $isFiscal = true
    ) {}

    public function failed(?Throwable $e): void
    {
        LimoPickupEvent::query()->whereKey($this->limoPickupEventId)->update([
            'email_sent' => LimoPickupEvent::EMAIL_NOT_SENT,
        ]);
        $mtid = LimoPickupEvent::query()->whereKey($this->limoPickupEventId)->value('merchant_transaction_id');
        Log::channel('payments')->error('limo_invoice_email_job_exhausted', [
            'limo_pickup_event_id' => $this->limoPickupEventId,
            'merchant_transaction_id' => $mtid,
            'is_fiscal' => $this->isFiscal,
            'message' => $e?->getMessage(),
            'exception' => $e !== null ? $e::class : null,
        ]);
    }

    public function handle(PaidInvoicePdfGenerator $pdfGenerator): void
    {
        /** @var LimoPickupEvent|null $event */
        $event = null;
        $claimed = false;

        DB::transaction(function () use (&$event, &$claimed): void {
            $event = LimoPickupEvent::query()
                ->whereKey($this->limoPickupEventId)
                ->lockForUpdate()
                ->first();

            if (! $event) {
                return;
            }

            if ($event->invoice_email_sent_at !== null) {
                return;
            }

            if ((int) $event->email_sent === LimoPickupEvent::EMAIL_SENDING) {
                return;
            }

            $event->update(['email_sent' => LimoPickupEvent::EMAIL_SENDING]);
            $claimed = true;
        });

        if (! $event || ! $claimed) {
            return;
        }

        $email = $event->agency_email_snapshot;
        if ($email === null || $email === '') {
            $event->update(['email_sent' => LimoPickupEvent::EMAIL_NOT_SENT]);

            return;
        }

        // Fiskalni Limo račun i prateći email su isključivo na crnogorskom (cg), nezavisno od users.lang.
        $invoiceEmailLocale = 'cg';

        $previousLocale = app()->getLocale();
        app()->setLocale($invoiceEmailLocale);

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $subjectTemplate = UiText::t(
            'emails',
            'limo_invoice_email_subject',
            'Limo račun %1$s',
            $invoiceEmailLocale
        );
        $subject = sprintf($subjectTemplate, $event->merchant_transaction_id);

        $body = $this->buildBody($event, $invoiceEmailLocale);

        $tmpPath = null;
        try {
            $pdfBinary = $pdfGenerator->renderLimoBinary($event, $this->isFiscal);
            if ($pdfBinary === '') {
                throw new RuntimeException('Limo invoice PDF empty after renderLimoBinary.');
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'bus_limo_inv_');
            if ($tmpPath === false) {
                throw new RuntimeException('tempnam failed for limo invoice PDF.');
            }

            file_put_contents($tmpPath, $pdfBinary);
            Mail::raw(
                $body,
                function ($message) use ($event, $email, $fromAddress, $fromName, $tmpPath, $subject): void {
                    $message->to($email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
                    $message->attach($tmpPath, [
                        'as' => 'invoice-limo-'.$event->id.'.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            );

            $event->markInvoiceEmailSent();
            Log::channel('payments')->info('limo_invoice_email_sent', [
                'limo_pickup_event_id' => $event->id,
                'merchant_transaction_id' => $event->merchant_transaction_id,
                'agency_user_id' => $event->agency_user_id,
                'is_fiscal_pdf' => $this->isFiscal,
            ]);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('limo_invoice_email_send_failed', [
                'limo_pickup_event_id' => $event->id,
                'merchant_transaction_id' => $event->merchant_transaction_id,
                'is_fiscal' => $this->isFiscal,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $event->update(['email_sent' => LimoPickupEvent::EMAIL_NOT_SENT]);
            throw $e;
        } finally {
            if (is_string($tmpPath)) {
                @unlink($tmpPath);
            }
            app()->setLocale($previousLocale);
        }
    }

    private function buildBody(LimoPickupEvent $event, string $emailLocale): string
    {
        $jirSuffix = '';
        if ($event->fiscal_jir) {
            $jirSuffix = "\n\n".sprintf(
                UiText::t('emails', 'paid_invoice_email_jir_line', 'JIR: %1$s', $emailLocale),
                $event->fiscal_jir
            );
        }

        $bodyTemplate = UiText::t(
            'emails',
            'limo_invoice_email_body',
            "Poštovani,\n\nU prilogu je PDF račun za Limo uslugu (transakcija %1\$s).%2\$s\n\nHvala.",
            $emailLocale
        );

        return sprintf(
            $bodyTemplate,
            $event->merchant_transaction_id,
            $jirSuffix
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\ScheduledAdminReportsMail;
use App\Models\AdminAlert;
use App\Models\ReportEmail;
use App\Models\ScheduledReportDelivery;
use App\Services\AdminPanel\Reports\AdminReportsService;
use App\Services\Pdf\AdminReportsPdfGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendScheduledAdminReports extends Command
{
    protected $signature = 'reports:send-scheduled {period : daily|monthly|yearly}';

    protected $description = 'Send scheduled admin PDF reports (daily/monthly/yearly) to report_emails recipients.';

    public function handle(AdminReportsService $reports, AdminReportsPdfGenerator $pdf): int
    {
        $periodType = (string) $this->argument('period');
        if (! in_array($periodType, ['daily', 'monthly', 'yearly'], true)) {
            $this->error('Invalid period. Expected daily|monthly|yearly.');
            return self::FAILURE;
        }

        $tz = 'Europe/Podgorica';
        [$from, $to, $subjectLabel, $fileLabel] = $this->resolvePeriod($periodType, $tz);

        $periodStart = $from->toDateString();
        $periodEnd = $to->toDateString();
        $recipientEmails = $this->normalizedReportRecipientEmails();

        if ($recipientEmails->isEmpty()) {
            Log::channel('payments')->info('scheduled_reports_no_recipients', [
                'period_type' => $periodType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
            $this->info('No recipients found in report_emails; skipped.');

            return self::SUCCESS;
        }

        // Generate all PDFs first (no partial emails).
        try {
            $attachments = $this->buildAllPdfs($reports, $pdf, $periodType, $from, $to, $fileLabel, $subjectLabel);
        } catch (Throwable $e) {
            $this->error(sprintf(
                'Fatal PDF generation failed before any recipient could be attempted: %s: %s',
                $e::class,
                mb_substr($e->getMessage(), 0, 500),
            ));
            $this->onPackageFailure(
                stage: 'generation',
                periodType: $periodType,
                from: $from,
                to: $to,
                error: $e->getMessage(),
                exceptionClass: $e::class
            );

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $failedRecipients = [];
        $skippedRecipients = [];
        $sentRecipients = [];
        $attachmentCount = count($attachments);

        foreach ($recipientEmails as $email) {
            try {
                $result = $this->processRecipient(
                    email: $email,
                    periodType: $periodType,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    subjectLabel: $subjectLabel,
                    attachments: $attachments,
                    attachmentCount: $attachmentCount,
                );

                match ($result['outcome']) {
                    'sent' => (function () use (&$sent, &$sentRecipients, $result): void {
                        $sent++;
                        $sentRecipients[] = $result;
                    })(),
                    'skipped' => (function () use (&$skipped, &$skippedRecipients, $result): void {
                        $skipped++;
                        $skippedRecipients[] = $result;
                    })(),
                    'failed' => (function () use (&$failed, &$failedRecipients, $result): void {
                        $failed++;
                        $failedRecipients[] = $result;
                    })(),
                    default => null,
                };
            } catch (Throwable $e) {
                $failed++;
                $failedRecipients[] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ];
                $this->logRecipientFailed(
                    periodType: $periodType,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    email: $email,
                    attachmentCount: $attachmentCount,
                    exception: $e,
                );
            }
        }

        if ($failed > 0 && $sent === 0 && $skipped === 0) {
            $this->onPackageFailure(
                stage: 'delivery',
                periodType: $periodType,
                from: $from,
                to: $to,
                error: collect($failedRecipients)
                    ->map(fn (array $row): string => sprintf(
                        '%s (%s: %s)',
                        $row['email'],
                        $row['exception'],
                        mb_substr((string) $row['error'], 0, 200),
                    ))
                    ->implode('; '),
                exceptionClass: 'ScheduledReportDeliveryFailure'
            );
        } elseif ($failed > 0) {
            $this->onPartialDeliveryFailure(
                periodType: $periodType,
                from: $from,
                to: $to,
                failedRecipients: $failedRecipients,
                sentCount: $sent,
                skippedCount: $skipped,
            );
        }

        $this->info(sprintf(
            'scheduled reports done: sent=%d, skipped=%d, failed=%d, recipients=%d',
            $sent,
            $skipped,
            $failed,
            $recipientEmails->count(),
        ));

        foreach ($sentRecipients as $row) {
            $this->line(sprintf('  sent: %s', $row['email']));
        }

        foreach ($skippedRecipients as $row) {
            $sentAt = $row['sent_at'] ? ' sent_at='.$row['sent_at'] : '';
            $this->line(sprintf(
                '  skipped: %s (%s%s)',
                $row['email'],
                $row['reason'],
                $sentAt,
            ));
        }

        foreach ($failedRecipients as $row) {
            $this->line(sprintf(
                '  failed: %s (%s: %s)',
                $row['email'],
                $row['exception'],
                mb_substr((string) $row['error'], 0, 200),
            ));
        }

        if ($failed > 0 && $sent === 0 && $skipped === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{filename:string,binary:string}>  $attachments
     * @return array{outcome: string, email: string, reason?: string, sent_at?: ?string, error?: string, exception?: string}
     */
    private function processRecipient(
        string $email,
        string $periodType,
        string $periodStart,
        string $periodEnd,
        string $subjectLabel,
        array $attachments,
        int $attachmentCount,
    ): array {
        $claim = $this->claimDelivery($periodType, $periodStart, $periodEnd, $email);
        if ($claim['delivery'] === null) {
            $this->logRecipientSkipped(
                periodType: $periodType,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                email: $email,
                reason: $claim['skip_reason'] ?? 'already_sent',
                sentAt: $claim['sent_at'] ?? null,
                attachmentCount: $attachmentCount,
            );

            return [
                'outcome' => 'skipped',
                'email' => $email,
                'reason' => $claim['skip_reason'] ?? 'already_sent',
                'sent_at' => $claim['sent_at'] ?? null,
            ];
        }

        $delivery = $claim['delivery'];
        $subject = $this->subjectFor($periodType, $subjectLabel);
        $body = $this->bodyFor($periodType, $subjectLabel);

        try {
            Mail::to($email)->send(new ScheduledAdminReportsMail(
                subjectLine: $subject,
                bodyText: $body,
                pdfAttachments: $attachments,
            ));

            $delivery->update([
                'status' => ScheduledReportDelivery::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $this->logRecipientSent(
                periodType: $periodType,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                email: $email,
                attachmentCount: $attachmentCount,
                attachments: $attachments,
            );

            return [
                'outcome' => 'sent',
                'email' => $email,
            ];
        } catch (Throwable $e) {
            $delivery->update([
                'status' => ScheduledReportDelivery::STATUS_FAILED,
                'sent_at' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            $this->logRecipientFailed(
                periodType: $periodType,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                email: $email,
                attachmentCount: $attachmentCount,
                exception: $e,
            );

            return [
                'outcome' => 'failed',
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ];
        }
    }

    private function logRecipientSent(
        string $periodType,
        string $periodStart,
        string $periodEnd,
        string $email,
        int $attachmentCount,
        array $attachments,
    ): void {
        Log::channel('payments')->info('scheduled_report_recipient_sent', [
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'recipient_email' => $email,
            'attachment_count' => $attachmentCount,
            'attachments' => array_map(fn (array $a) => $a['filename'], $attachments),
        ]);
    }

    private function logRecipientSkipped(
        string $periodType,
        string $periodStart,
        string $periodEnd,
        string $email,
        string $reason,
        ?string $sentAt,
        int $attachmentCount,
    ): void {
        Log::channel('payments')->info('scheduled_report_recipient_skipped', [
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'recipient_email' => $email,
            'reason' => $reason,
            'sent_at' => $sentAt,
            'attachment_count' => $attachmentCount,
        ]);
    }

    private function logRecipientFailed(
        string $periodType,
        string $periodStart,
        string $periodEnd,
        string $email,
        int $attachmentCount,
        Throwable $exception,
    ): void {
        Log::channel('payments')->warning('scheduled_report_recipient_failed', [
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'recipient_email' => $email,
            'attachment_count' => $attachmentCount,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    /**
     * @param  list<array{email: string, error?: string, exception?: string}>  $failedRecipients
     */
    private function onPartialDeliveryFailure(
        string $periodType,
        Carbon $from,
        Carbon $to,
        array $failedRecipients,
        int $sentCount,
        int $skippedCount,
    ): void {
        $failedSummary = collect($failedRecipients)
            ->map(fn (array $row): string => sprintf(
                '%s (%s: %s)',
                $row['email'],
                $row['exception'] ?? 'Exception',
                mb_substr((string) ($row['error'] ?? ''), 0, 200),
            ))
            ->implode('; ');

        $title = sprintf(
            'Scheduled admin reports partial failure (delivery): %s – %s',
            $periodType,
            $from->toDateString(),
        );
        $message = sprintf(
            'Djelimičan neuspjeh slanja zakazanih izvještaja za period %s .. %s. Poslato: %d, preskočeno: %d, neuspjelo: %d. Neuspjeli: %s',
            $from->toDateString(),
            $to->toDateString(),
            $sentCount,
            $skippedCount,
            count($failedRecipients),
            mb_substr($failedSummary, 0, 500),
        );

        $exists = AdminAlert::query()
            ->where('type', 'scheduled_admin_reports_failed')
            ->where('title', $title)
            ->whereNull('removed_at')
            ->exists();

        if (! $exists) {
            AdminAlert::query()->create([
                'type' => 'scheduled_admin_reports_failed',
                'status' => AdminAlert::STATUS_UNREAD,
                'title' => $title,
                'message' => $message,
                'payload_json' => [
                    'stage' => 'partial_delivery',
                    'period_type' => $periodType,
                    'period_start' => $from->toDateString(),
                    'period_end' => $to->toDateString(),
                    'sent_count' => $sentCount,
                    'skipped_count' => $skippedCount,
                    'failed_recipients' => $failedRecipients,
                ],
            ]);
        }

        Log::channel('payments')->error('scheduled_reports_partial_delivery_failed', [
            'period_type' => $periodType,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'sent_count' => $sentCount,
            'skipped_count' => $skippedCount,
            'failed_recipients' => $failedRecipients,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function normalizedReportRecipientEmails(): \Illuminate\Support\Collection
    {
        return ReportEmail::allRecipients()
            ->pluck('email')
            ->map(fn ($email) => $this->normalizeRecipientEmail((string) $email))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values();
    }

    private function normalizeRecipientEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:string,3:string} from,to,subjectLabel,fileLabel
     */
    private function resolvePeriod(string $periodType, string $tz): array
    {
        $now = Carbon::now($tz);

        if ($periodType === 'daily') {
            $d = $now->copy()->subDay()->startOfDay();
            return [$d, $d, $d->format('d.m.Y').'.', $d->format('Y-m-d')];
        }

        if ($periodType === 'monthly') {
            $from = $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
            $to = $from->copy()->endOfMonth()->startOfDay();
            return [$from, $to, $from->format('m.Y').'.', $from->format('Y-m')];
        }

        // yearly
        $year = (int) $now->copy()->subYear()->format('Y');
        $from = Carbon::create($year, 1, 1, 0, 0, 0, $tz)->startOfDay();
        $to = Carbon::create($year, 12, 31, 0, 0, 0, $tz)->startOfDay();

        return [$from, $to, $from->format('Y').'.', $from->format('Y')];
    }

    /**
     * @return array<int, array{filename:string,binary:string}>
     */
    private function buildAllPdfs(
        AdminReportsService $reports,
        AdminReportsPdfGenerator $pdf,
        string $periodType,
        Carbon $from,
        Carbon $to,
        string $fileLabel,
        string $subjectLabel,
    ): array {
        $prefix = match ($periodType) {
            'daily' => 'dnevni',
            'monthly' => 'mjesečni',
            default => 'godišnji',
        };

        $attachments = [];

        try {
            $dataset = [
                'title' => 'Finansijski izvještaj po uplati za '.$subjectLabel,
                'subtitle' => 'Uplate za rezervacije',
                'kind' => 'by_payment',
                'period' => $subjectLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byPayment($from, $to),
            ];
            $attachments[] = [
                'filename' => "{$prefix}-po-uplati-{$fileLabel}.pdf",
                'binary' => $pdf->renderBinary($dataset),
            ];
        } catch (Throwable $e) {
            throw new \RuntimeException('PDF generation failed for report kind by_payment: '.$e->getMessage(), 0, $e);
        }

        if ((bool) config('features.advance_payments')) {
            try {
                $snapshot = $to->copy()->setTimezone('Europe/Podgorica')->endOfDay();
                $dataset = [
                    'title' => 'Izvještaj o obavezama po osnovu avansnih uplata na dan '.$this->fmtDateCg($to),
                    'subtitle' => 'Prikaz predstavlja stanje neiskorišćenih avansnih sredstava po agencijama na izabrani dan.',
                    'kind' => 'advance_obligations',
                    'period' => $this->fmtDateCg($to),
                    'from' => $to->toDateString(),
                    'to' => $to->toDateString(),
                    'data' => $reports->advanceObligationsSnapshot($snapshot),
                ];
                $attachments[] = [
                    'filename' => "{$prefix}-obaveze-po-avansu-{$fileLabel}.pdf",
                    'binary' => $pdf->renderBinary($dataset),
                ];
            } catch (Throwable $e) {
                throw new \RuntimeException('PDF generation failed for report kind advance_obligations: '.$e->getMessage(), 0, $e);
            }
        } else {
            Log::channel('payments')->info('scheduled_reports_advance_obligations_skipped_feature_off', [
                'period_type' => $periodType,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ]);
        }

        try {
            $dataset = [
                'title' => 'Izvještaj po tipu rezervacije za '.$subjectLabel,
                'subtitle' => 'Prihodi po tipu rezervacije',
                'kind' => 'by_reservation_type',
                'period' => $subjectLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byReservationType($from, $to),
            ];
            $attachments[] = [
                'filename' => "{$prefix}-po-tipu-rezervacije-{$fileLabel}.pdf",
                'binary' => $pdf->renderBinary($dataset),
            ];
        } catch (Throwable $e) {
            throw new \RuntimeException('PDF generation failed for report kind by_reservation_type: '.$e->getMessage(), 0, $e);
        }

        try {
            $dataset = [
                'title' => 'Izvještaj po tipu vozila za '.$subjectLabel,
                'subtitle' => 'Realizovane rezervacije po tipu vozila',
                'kind' => 'by_vehicle_type',
                'period' => $subjectLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byVehicleType($from, $to),
            ];
            $attachments[] = [
                'filename' => "{$prefix}-po-tipu-vozila-{$fileLabel}.pdf",
                'binary' => $pdf->renderBinary($dataset),
            ];
        } catch (Throwable $e) {
            throw new \RuntimeException('PDF generation failed for report kind by_vehicle_type: '.$e->getMessage(), 0, $e);
        }

        return $attachments;
    }

    private function subjectFor(string $periodType, string $label): string
    {
        return match ($periodType) {
            'daily' => 'Dnevni izvještaji – '.$label,
            'monthly' => 'Mjesečni izvještaji – '.$label,
            default => 'Godišnji izvještaji – '.$label,
        };
    }

    private function bodyFor(string $periodType, string $label): string
    {
        $lines = [
            'Poštovani,',
            '',
            match ($periodType) {
                'daily' => 'U prilogu su dnevni izvještaji za '.$label,
                'monthly' => 'U prilogu su mjesečni izvještaji za '.$label,
                default => 'U prilogu su godišnji izvještaji za '.$label,
            },
            '',
            'Srdačan pozdrav,',
            'Kotor Bus',
        ];

        return implode("\n", $lines);
    }

    private function fmtDateCg(Carbon $d): string
    {
        return $d->format('d.m.Y').'.';
    }

    /**
     * Claim delivery row for this recipient/period. Returns null delivery when already sent.
     *
     * @return array{delivery: ?ScheduledReportDelivery, skip_reason: ?string, sent_at: ?string}
     */
    private function claimDelivery(string $periodType, string $periodStart, string $periodEnd, string $email): array
    {
        $email = $this->normalizeRecipientEmail($email);

        return DB::transaction(function () use ($periodType, $periodStart, $periodEnd, $email): array {
            $row = ScheduledReportDelivery::query()
                ->where('period_type', $periodType)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->where('recipient_email', $email)
                ->lockForUpdate()
                ->first();

            if ($row && $row->status === ScheduledReportDelivery::STATUS_SENT) {
                return [
                    'delivery' => null,
                    'skip_reason' => 'already_sent',
                    'sent_at' => $row->sent_at?->toDateTimeString(),
                ];
            }

            if (! $row) {
                $row = ScheduledReportDelivery::query()->create([
                    'period_type' => $periodType,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'recipient_email' => $email,
                    'status' => ScheduledReportDelivery::STATUS_SENDING,
                    'sent_at' => null,
                    'error_message' => null,
                ]);
            } else {
                $row->update([
                    'status' => ScheduledReportDelivery::STATUS_SENDING,
                    'sent_at' => null,
                    'error_message' => null,
                ]);
            }

            return [
                'delivery' => $row,
                'skip_reason' => null,
                'sent_at' => null,
            ];
        });
    }

    private function onPackageFailure(
        string $stage,
        string $periodType,
        Carbon $from,
        Carbon $to,
        string $error,
        string $exceptionClass,
    ): void {
        $title = sprintf(
            'Scheduled admin reports failed (%s): %s – %s',
            $stage,
            $periodType,
            $from->toDateString()
        );
        $message = sprintf(
            'Neuspjeh slanja zakazanih izvještaja (%s) za period %s .. %s. Greška: %s',
            $stage,
            $from->toDateString(),
            $to->toDateString(),
            mb_substr($error, 0, 200)
        );

        // Idempotent alert (do not duplicate on retries).
        $exists = AdminAlert::query()
            ->where('type', 'scheduled_admin_reports_failed')
            ->where('title', $title)
            ->whereNull('removed_at')
            ->exists();
        if (! $exists) {
            AdminAlert::query()->create([
                'type' => 'scheduled_admin_reports_failed',
                'status' => AdminAlert::STATUS_UNREAD,
                'title' => $title,
                'message' => $message,
                'payload_json' => [
                    'stage' => $stage,
                    'period_type' => $periodType,
                    'period_start' => $from->toDateString(),
                    'period_end' => $to->toDateString(),
                    'error_message' => $error,
                    'exception' => $exceptionClass,
                ],
            ]);
        }

        $subject = '[Kotor Bus] Scheduled admin reports failed';
        $body = implode("\n", [
            'Neuspjeh slanja zakazanih admin izvještaja.',
            '',
            'stage: '.$stage,
            'period_type: '.$periodType,
            'period_start: '.$from->toDateString(),
            'period_end: '.$to->toDateString(),
            'error: '.$error,
            'exception: '.$exceptionClass,
        ]);

        try {
            Mail::raw($body, function ($m) use ($subject): void {
                $m->to('bus@kotor.me')
                    ->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject($subject);
            });
        } catch (Throwable $e) {
            Log::channel('payments')->error('scheduled_reports_failure_email_failed', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        Log::channel('payments')->error('scheduled_reports_generation_failed', [
            'stage' => $stage,
            'period_type' => $periodType,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'message' => $error,
            'exception' => $exceptionClass,
        ]);
    }
}


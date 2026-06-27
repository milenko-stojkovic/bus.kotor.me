<?php

namespace App\Services\AdminPanel;

use App\Models\AdminAlert;
use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * Informational admin alerts when a reservation enters post-fiscalization (deduped per reservation).
 */
final class PostFiscalizationAdminAlertService
{
    public const TYPE = 'post_fiscalization_started';

    public function notifyStarted(
        Reservation $reservation,
        PostFiscalizationData $post,
        ?string $resolutionReason = null,
    ): ?AdminAlert {
        $dedupeKey = 'post_fiscalization_started:'.$reservation->id;

        $intro = 'Rezervacija je ušla u naknadnu fiskalizaciju jer fiskalni servis trenutno nije bio dostupan. '
            .'Sistem će narednih 24 sata automatski pokušavati fiskalizaciju.';

        $contextLines = [
            '',
            '--- detalji ---',
            'reservation_id: '.$reservation->id,
            'merchant_transaction_id: '.($reservation->merchant_transaction_id ?? '—'),
            'email: '.($reservation->email ?? '—'),
            'reservation_date: '.($reservation->reservation_date?->format('Y-m-d') ?? '—'),
            'amount: '.($reservation->invoice_amount ?? '—'),
            'resolution_reason: '.($resolutionReason ?? '—'),
            'fiscal_error: '.($post->error ?? '—'),
        ];

        $message = $intro."\n".implode("\n", $contextLines);

        $alert = app(AdminAlertService::class)->createOnce(
            self::TYPE,
            'Naknadna fiskalizacija — rezervacija #'.$reservation->id,
            $message,
            'info',
            $dedupeKey,
            [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'email' => $reservation->email,
                'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
                'amount' => $reservation->invoice_amount,
                'resolution_reason' => $resolutionReason,
                'fiscal_error' => $post->error,
            ],
        );

        if ($alert !== null) {
            $alert->update([
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
            ]);

            Log::channel('payments')->info('post_fiscalization_info_admin_alert_created', [
                'reservation_id' => $reservation->id,
                'admin_alert_id' => $alert->id,
            ]);
        }

        return $alert;
    }

    public function resolveStarted(int $reservationId): void
    {
        $dedupeKey = 'post_fiscalization_started:'.$reservationId;

        $updated = AdminAlert::query()
            ->where('type', self::TYPE)
            ->whereNull('removed_at')
            ->whereNot('status', AdminAlert::STATUS_DONE)
            ->where('payload_json->dedupe_key', $dedupeKey)
            ->update([
                'status' => AdminAlert::STATUS_DONE,
                'resolved_at' => now(),
            ]);

        if ($updated > 0) {
            Log::channel('payments')->info('post_fiscalization_info_admin_alert_resolved', [
                'reservation_id' => $reservationId,
                'rows_updated' => $updated,
            ]);
        }
    }
}

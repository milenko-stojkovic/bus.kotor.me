<?php

namespace App\Services;

use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminFiscalizationAlertService
{
    public const ADMIN_EMAIL = 'bus@kotor.me';

    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $subject, string $body, array $context = []): void
    {
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        try {
            Mail::raw($body, function ($message) use ($subject, $fromAddress, $fromName): void {
                $message->to(self::ADMIN_EMAIL)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
            });

            Log::channel('payments')->warning('Admin fiscalization email sent', [
                'to' => self::ADMIN_EMAIL,
                'subject' => $subject,
                ...$context,
            ]);
        } catch (\Throwable $e) {
            Log::channel('payments')->error('Admin fiscalization email failed', [
                'to' => self::ADMIN_EMAIL,
                'subject' => $subject,
                'error' => $e->getMessage(),
                ...$context,
            ]);
        }
    }

    public function buildReservationContext(Reservation $r): string
    {
        return implode("\n", array_filter([
            'reservation_id: '.$r->id,
            'merchant_transaction_id: '.($r->merchant_transaction_id ?? '—'),
            'date: '.($r->reservation_date?->format('Y-m-d') ?? '—'),
            'drop_off_time_slot_id: '.($r->drop_off_time_slot_id ?? '—'),
            'pick_up_time_slot_id: '.($r->pick_up_time_slot_id ?? '—'),
            'user_name: '.($r->user_name ?? '—'),
            'email: '.($r->email ?? '—'),
            'country: '.($r->country ?? '—'),
            'license_plate: '.($r->license_plate ?? '—'),
            'vehicle_type_id: '.($r->vehicle_type_id ?? '—'),
            'status: '.($r->status ?? '—'),
            'fiscal_jir: '.($r->fiscal_jir ?? '—'),
            'fiscal_ikof: '.($r->fiscal_ikof ?? '—'),
            'created_at: '.($r->created_at?->toDateTimeString() ?? '—'),
        ]));
    }

    public function buildPostRowContext(PostFiscalizationData $p): string
    {
        return implode("\n", array_filter([
            'post_fiscalization_data_id: '.$p->id,
            'attempts: '.($p->attempts ?? 0),
            'error: '.($p->error ?? '—'),
            'next_retry_at: '.($p->next_retry_at?->toDateTimeString() ?? '—'),
            'resolved_at: '.($p->resolved_at?->toDateTimeString() ?? '—'),
            'admin_notified_at: '.($p->admin_notified_at?->toDateTimeString() ?? '—'),
            'created_at: '.($p->created_at?->toDateTimeString() ?? '—'),
        ]));
    }
}


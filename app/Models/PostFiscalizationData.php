<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Podaci za naknadnu fiskalizaciju – slog po rezervaciji kada fiskalizacija ne uspe odmah.
 *
 * FK ka Reservation. error, attempts, next_retry_at za retry pipeline.
 * Nakon uspešne naknadne fiskalizacije: upisati fiscal_* u Reservation i obrisati slog (applyFiscalDataAndDelete).
 */
class PostFiscalizationData extends Model
{
    protected $table = 'post_fiscalization_data';

    protected $fillable = [
        'reservation_id',
        'merchant_transaction_id',
        'error',
        'attempts',
        'next_retry_at',
        'resolved_at',
        'admin_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'next_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
            'admin_notified_at' => 'datetime',
        ];
    }

    /** Scope: samo nerešeni (cron i admin "retry" koriste). */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /** FK ka Reservation. */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /**
     * Nakon uspešne naknadne fiskalizacije: upiše fiscal podatke u Reservation i obriše ovaj slog.
     *
     * @param  array{fiscal_jir?: string, fiscal_ikof?: string, fiscal_qr?: string, fiscal_operator?: string, fiscal_date?: string|\DateTimeInterface}  $fiscalData
     */
    public function applyFiscalDataAndDelete(array $fiscalData): void
    {
        $reservation = $this->reservation;
        if (! $reservation) {
            $this->delete();
            return;
        }

        $reservation->update(array_filter([
            'fiscal_jir' => $fiscalData['fiscal_jir'] ?? null,
            'fiscal_ikof' => $fiscalData['fiscal_ikof'] ?? null,
            'fiscal_qr' => $fiscalData['fiscal_qr'] ?? null,
            'fiscal_operator' => $fiscalData['fiscal_operator'] ?? null,
            'fiscal_date' => $fiscalData['fiscal_date'] ?? null,
        ], fn ($v) => $v !== null));

        $this->delete();
    }
}

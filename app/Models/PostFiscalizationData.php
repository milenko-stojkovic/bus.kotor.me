<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Podaci za naknadnu fiskalizaciju – slog po rezervaciji dok fiskalni podaci nisu primljeni.
 *
 * FK ka Reservation. Nakon uspešne naknadne fiskalizacije: upisati fiscal_jir, fiscal_ikof,
 * fiscal_qr, fiscal_operator, fiscal_date u Reservation i obrisati ovaj slog (v. applyFiscalDataAndDelete).
 */
class PostFiscalizationData extends Model
{
    protected $table = 'post_fiscalization_data';

    protected $fillable = [
        'reservation_id',
        'merchant_transaction_id',
    ];

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

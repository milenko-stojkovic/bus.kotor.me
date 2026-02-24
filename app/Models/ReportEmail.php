<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Lista email adresa za slanje potvrda i obaveštenja (npr. potvrda rezervacije, izveštaji).
 *
 * Integracija sa Reservation: pri slanju potvrde rezervacije korisniku, označiti
 * Reservation->email_sent (npr. $reservation->markConfirmationEmailSent()) da se ne šalje duplo.
 */
class ReportEmail extends Model
{
    protected $table = 'report_emails';

    /** Tabela nema updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Svi aktivni email-ovi za obaveštenja (npr. za CC/BCC na izveštaje).
     *
     * @return Collection<int, ReportEmail>
     */
    public static function allRecipients(): Collection
    {
        return static::query()->orderBy('id')->get();
    }
}

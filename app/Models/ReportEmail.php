<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Email adrese za obaveštenja: zakazani admin PDF izvještaji (`purpose=report`) i Limo incidenti (`purpose=limo_incidents`).
 *
 * Integracija sa Reservation: pri slanju potvrde rezervacije korisniku, označiti
 * Reservation->email_sent (npr. $reservation->markConfirmationEmailSent()) da se ne šalje duplo.
 */
class ReportEmail extends Model
{
    protected $table = 'report_emails';

    /** Tabela nema updated_at. */
    public const UPDATED_AT = null;

    public const PURPOSE_REPORT = 'report';

    public const PURPOSE_LIMO_INCIDENTS = 'limo_incidents';

    protected $fillable = [
        'email',
        'purpose',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'purpose' => 'string',
        ];
    }

    public function scopeForReport(Builder $query): void
    {
        $query->where('purpose', self::PURPOSE_REPORT);
    }

    public function scopeForLimoIncidents(Builder $query): void
    {
        $query->where('purpose', self::PURPOSE_LIMO_INCIDENTS);
    }

    /**
     * Svi aktivni email-ovi za obaveštenja (npr. za CC/BCC na izveštaje).
     *
     * @return Collection<int, ReportEmail>
     */
    public static function allRecipients(): Collection
    {
        return static::query()->forReport()->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    public static function limoIncidentRecipientEmailsOrdered(): array
    {
        return static::query()
            ->forLimoIncidents()
            ->orderBy('id')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();
    }
}

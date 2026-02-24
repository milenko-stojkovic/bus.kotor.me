<?php

namespace App\Jobs;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Nakon uspešnog plaćanja: pokreće fiskalizaciju za rezervaciju.
 * Stub – prava fiskalizacija (API poziv) dodaje se kasnije. Ne vezuj fiskalizaciju za HTTP request.
 */
class PostFiscalizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $reservationId
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (! $reservation) {
            return;
        }

        // TODO: poziv fiskalnog servisa; na uspeh ažuriraj fiscal_* na rezervaciji
        // ili kreiraj PostFiscalizationData ako čekaš naknadnu fiskalizaciju
    }
}

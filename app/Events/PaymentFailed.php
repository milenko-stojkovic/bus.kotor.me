<?php

namespace App\Events;

use App\Models\TempData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emituje se kada je plaćanje otkazano ili nije uspelo (CANCEL/ERROR/failed).
 * Frontend / notifier može koristiti za redirect + poruku; listeneri za log i email.
 */
class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TempData $tempData
    ) {}
}

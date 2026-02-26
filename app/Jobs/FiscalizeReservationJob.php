<?php

namespace App\Jobs;

/**
 * @deprecated Use ProcessReservationAfterPaymentJob (fiscalization + fallback to non-fiscal invoice).
 */
class FiscalizeReservationJob extends ProcessReservationAfterPaymentJob
{
}

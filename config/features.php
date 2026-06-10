<?php

return [
    'advance_payments' => env('ADVANCE_PAYMENTS_ENABLED', false),
    'limo_service' => env('LIMO_SERVICE_ENABLED', false),
    /** @deprecated QR scan/generate workflow; informational Limo page stays on when limo_service is on. */
    'limo_qr_workflow' => env('LIMO_QR_WORKFLOW_ENABLED', false),
];


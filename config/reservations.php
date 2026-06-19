<?php

return [
    /*
    | Vremenska zona za operativne termine (list_of_time_slots su lokalni sati).
    | Control „dolasci po terminima“ koriste 1 h prije početka; admin scope nextThreeHours i dalje 3 h.
    | Podrazumijevano = APP_TIMEZONE (vidi config/app.php).
    */
    'operations_timezone' => env('RESERVATIONS_OPERATIONS_TIMEZONE', env('APP_TIMEZONE', 'Europe/Podgorica')),

    /*
    | Expire pending temp_data after this many minutes (ExpirePendingReservations cron).
    */
    'pending_expire_minutes' => (int) env('RESERVATIONS_PENDING_EXPIRE_MINUTES', 5),

    /*
    | Cleanup temp_data rows older than this many days (CleanupOldTempData cron).
    */
    'temp_data_retention_days' => (int) env('TEMP_DATA_RETENTION_DAYS', env('RESERVATIONS_TEMP_DATA_RETENTION_DAYS', 180)),

    /*
    | Retry token validity: failed payment form data is available for retry for this many minutes.
    */
    'retry_token_valid_minutes' => (int) env('RESERVATIONS_RETRY_TOKEN_VALID_MINUTES', 60),
];

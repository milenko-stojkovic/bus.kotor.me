<?php

return [
    /*
    | Expire pending temp_data after this many minutes (ExpirePendingReservations cron).
    */
    'pending_expire_minutes' => (int) env('RESERVATIONS_PENDING_EXPIRE_MINUTES', 30),

    /*
    | Cleanup temp_data rows older than this many days (CleanupOldTempData cron).
    */
    'temp_data_retention_days' => (int) env('RESERVATIONS_TEMP_DATA_RETENTION_DAYS', 7),
];

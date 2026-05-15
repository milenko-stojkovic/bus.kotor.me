<?php

namespace App\Support;

/**
 * Cache keys for operational heartbeats (future read-only “Sistem status” dashboard).
 * Values use default Laravel cache store; TTL long enough for daily/six-hour schedules.
 */
final class OperationalHeartbeatCache
{
    /** 30 days — must survive gaps between scheduled runs. */
    public const TTL_SECONDS = 60 * 60 * 24 * 30;

    public const SYSTEM_HEALTH_LAST_RUN_AT = 'system_health:last_run_at';

    public const SYSTEM_HEALTH_LAST_OK_AT = 'system_health:last_ok_at';

    public const MEGA_LAST_DIAGNOSE_AT = 'mega:last_diagnose_at';

    public const MEGA_LAST_DIAGNOSE_OK = 'mega:last_diagnose_ok';

    public const MEGA_LAST_DIAGNOSE_ERROR = 'mega:last_diagnose_error';

    public const ARCHIVE_PRIVATE_LAST_RUN_AT = 'archive_private:last_run_at';

    public const ARCHIVE_PRIVATE_LAST_OK_AT = 'archive_private:last_ok_at';

    public const ARCHIVE_PRIVATE_LAST_SUMMARY = 'archive_private:last_summary';

    public static function ttl(): \DateTimeInterface
    {
        return now()->addSeconds(self::TTL_SECONDS);
    }
}

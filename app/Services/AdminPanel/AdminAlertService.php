<?php

namespace App\Services\AdminPanel;

use App\Models\AdminAlert;

/**
 * Deduped admin operational alerts (payload_json.dedupe_key + type).
 */
final class AdminAlertService
{
    /**
     * Create an alert only if no open alert exists with the same type and dedupe_key.
     *
     * @param  array<string, mixed>  $payload  Merged with dedupe_key and severity when set
     * @return AdminAlert|null The new alert, or null if duplicate open alert exists
     */
    public function createOnce(
        string $type,
        string $title,
        string $message,
        ?string $severity = null,
        ?string $dedupeKey = null,
        array $payload = [],
    ): ?AdminAlert {
        $extra = array_filter([
            'dedupe_key' => $dedupeKey,
            'severity' => $severity,
        ], fn ($v) => $v !== null && $v !== '');

        $payloadJson = array_merge($payload, $extra);

        if ($dedupeKey !== null) {
            $exists = AdminAlert::query()
                ->where('type', $type)
                ->whereNull('removed_at')
                ->whereNot('status', AdminAlert::STATUS_DONE)
                ->where('payload_json->dedupe_key', $dedupeKey)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        return AdminAlert::query()->create([
            'type' => $type,
            'status' => AdminAlert::STATUS_UNREAD,
            'title' => $title,
            'message' => $message,
            'payload_json' => $payloadJson === [] ? null : $payloadJson,
        ]);
    }
}

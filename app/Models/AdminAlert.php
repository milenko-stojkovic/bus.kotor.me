<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAlert extends Model
{
    public const STATUS_UNREAD = 'unread';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'type',
        'status',
        'title',
        'message',
        'payload_json',
        'merchant_transaction_id',
        'temp_data_id',
        'reservation_id',
        'resolved_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'resolved_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function tempData(): BelongsTo
    {
        return $this->belongsTo(TempData::class, 'temp_data_id');
    }

    /**
     * Tekst za dugme Copy details (naslov, poruka, JSON payload).
     */
    public function copyDetailsText(): string
    {
        $parts = [
            $this->title,
            '',
            $this->message,
            '',
        ];
        $payload = $this->payload_json;
        $parts[] = $payload !== null && $payload !== []
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '(no payload)';

        return implode("\n", $parts);
    }
}

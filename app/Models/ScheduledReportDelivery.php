<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledReportDelivery extends Model
{
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'scheduled_report_deliveries';

    protected $fillable = [
        'period_type',
        'period_start',
        'period_end',
        'recipient_email',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}


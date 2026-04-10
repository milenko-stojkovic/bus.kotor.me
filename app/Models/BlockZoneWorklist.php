<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockZoneWorklist extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_READY_TO_ADJUST = 'ready_to_adjust';

    protected $table = 'block_zone_worklist';

    protected $fillable = [
        'merchant_transaction_id',
        'status',
        'old_date',
        'old_drop_off',
        'old_pick_up',
        'affected_drop_off',
        'affected_pick_up',
        'snapshot_json',
        'reservation_id',
        'temp_data_id',
    ];

    protected function casts(): array
    {
        return [
            'old_date' => 'date',
            'affected_drop_off' => 'boolean',
            'affected_pick_up' => 'boolean',
            'snapshot_json' => 'array',
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
}


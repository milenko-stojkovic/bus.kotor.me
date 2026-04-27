<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgencyAdvanceTopup extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'agency_advance_topups';

    protected $fillable = [
        'agency_user_id',
        'merchant_transaction_id',
        'amount',
        'status',
        'bank_payload',
        'paid_at',
        'failed_at',
        'confirmation_sent_at',
        'confirmation_email',
        'confirmation_sending_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bank_payload' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmation_sending_at' => 'datetime',
            'agency_user_id' => 'integer',
        ];
    }

    public function agencyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_user_id');
    }
}


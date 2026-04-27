<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgencyAdvanceTransaction extends Model
{
    public const TYPE_TOPUP = 'topup';
    public const TYPE_USAGE = 'usage';
    public const TYPE_CORRECTION = 'correction';

    protected $table = 'agency_advance_transactions';

    protected $fillable = [
        'agency_user_id',
        'amount',
        'type',
        'reference_type',
        'reference_id',
        'merchant_transaction_id',
        'note',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reference_id' => 'integer',
            'agency_user_id' => 'integer',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function agencyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_user_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}


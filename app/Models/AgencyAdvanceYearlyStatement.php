<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgencyAdvanceYearlyStatement extends Model
{
    protected $table = 'agency_advance_yearly_statements';

    protected $fillable = [
        'agency_user_id',
        'year',
        'sent_at',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'agency_user_id' => 'integer',
            'year' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function agencyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_user_id');
    }
}


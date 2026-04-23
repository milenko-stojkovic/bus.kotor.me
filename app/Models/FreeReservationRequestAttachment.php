<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeReservationRequestAttachment extends Model
{
    protected $table = 'free_reservation_request_attachments';

    protected $fillable = [
        'request_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FreeReservationRequest::class, 'request_id');
    }
}


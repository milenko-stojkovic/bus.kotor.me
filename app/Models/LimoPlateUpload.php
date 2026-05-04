<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimoPlateUpload extends Model
{
    protected $fillable = [
        'upload_token',
        'path',
        'ocr_text',
        'gps_lat',
        'gps_lng',
        'device_info',
        'uploaded_by_limo_admin_id',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_limo_admin_id');
    }
}

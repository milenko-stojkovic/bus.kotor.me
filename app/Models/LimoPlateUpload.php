<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimoPlateUpload extends Model
{
    protected $fillable = [
        'upload_token',
        'path',
        'plate_crop_left_bp',
        'plate_crop_top_bp',
        'plate_crop_width_bp',
        'plate_crop_height_bp',
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

    /**
     * @return array{left:int,top:int,width:int,height:int}|null
     */
    public function plateCropBasisPoints(): ?array
    {
        if ($this->plate_crop_width_bp === null || $this->plate_crop_height_bp === null) {
            return null;
        }

        return [
            'left' => (int) $this->plate_crop_left_bp,
            'top' => (int) $this->plate_crop_top_bp,
            'width' => (int) $this->plate_crop_width_bp,
            'height' => (int) $this->plate_crop_height_bp,
        ];
    }
}

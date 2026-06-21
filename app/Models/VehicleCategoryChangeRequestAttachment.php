<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VehicleCategoryChangeRequestAttachment extends Model
{
    protected $table = 'vehicle_category_change_request_attachments';

    protected $fillable = [
        'vehicle_category_change_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_category_change_request_id' => 'integer',
            'size' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(VehicleCategoryChangeRequest::class, 'vehicle_category_change_request_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VehicleCategoryChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'vehicle_category_change_requests';

    protected $fillable = [
        'user_id',
        'old_vehicle_id',
        'license_plate',
        'old_vehicle_type_id',
        'requested_vehicle_type_id',
        'status',
        'document_original_name',
        'document_path',
        'document_mime_type',
        'document_size_bytes',
        'locale',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'old_vehicle_id' => 'integer',
            'old_vehicle_type_id' => 'integer',
            'requested_vehicle_type_id' => 'integer',
            'document_size_bytes' => 'integer',
            'reviewed_by_admin_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function oldVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'old_vehicle_id');
    }

    public function oldVehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'old_vehicle_type_id');
    }

    public function requestedVehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'requested_vehicle_type_id');
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeReservationRequestVehicle extends Model
{
    protected $table = 'free_reservation_request_vehicles';

    protected $fillable = [
        'request_id',
        'segment_id',
        'agency_vehicle_id',
        'license_plate',
        'vehicle_type_id',
        'vehicle_type_label',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FreeReservationRequest::class, 'request_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(FreeReservationRequestSegment::class, 'segment_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}


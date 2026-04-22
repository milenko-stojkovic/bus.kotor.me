<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeReservationRequestVehicle extends Model
{
    protected $table = 'free_reservation_request_vehicles';

    protected $fillable = [
        'request_id',
        'license_plate',
        'vehicle_type_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FreeReservationRequest::class, 'request_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}


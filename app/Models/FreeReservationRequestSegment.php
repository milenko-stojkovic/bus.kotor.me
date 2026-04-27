<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreeReservationRequestSegment extends Model
{
    protected $table = 'free_reservation_request_segments';

    protected $fillable = [
        'request_id',
        'reservation_date',
        'drop_off_time_slot_id',
        'pick_up_time_slot_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FreeReservationRequest::class, 'request_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(FreeReservationRequestVehicle::class, 'segment_id');
    }

    public function dropOffTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'drop_off_time_slot_id');
    }

    public function pickUpTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'pick_up_time_slot_id');
    }
}


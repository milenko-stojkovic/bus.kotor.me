<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreeReservationRequest extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'locale',
        'institution_name',
        'institution_email',
        'institution_phone',
        'reservation_date',
        'drop_off_time_slot_id',
        'pick_up_time_slot_id',
        'country',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(FreeReservationRequestVehicle::class, 'request_id');
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


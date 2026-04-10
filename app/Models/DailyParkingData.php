<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dnevni kapacitet po terminu (datum + time_slot). Koristi se za validaciju dostupnih termina i kapaciteta.
 *
 * Relacija: belongsTo(TimeSlot) preko time_slot_id.
 * capacity = ukupno mesta, reserved = potvrđene rezervacije, pending = u toku (soft lock).
 * Slobodna mesta = capacity - reserved - pending (v. availableCapacity()).
 */
class DailyParkingData extends Model
{
    protected $table = 'daily_parking_data';

    protected $fillable = [
        'date',
        'time_slot_id',
        'capacity',
        'reserved',
        'pending',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
            'reserved' => 'integer',
            'pending' => 'integer',
            'is_blocked' => 'boolean',
        ];
    }

    /** FK ka TimeSlot (list_of_time_slots). */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'time_slot_id');
    }

    /** Broj slobodnih mesta za validaciju (capacity - reserved - pending). */
    public function availableCapacity(): int
    {
        return max(0, $this->capacity - $this->reserved - $this->pending);
    }
}

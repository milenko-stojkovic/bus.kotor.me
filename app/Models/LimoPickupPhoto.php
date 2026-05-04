<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimoPickupPhoto extends Model
{
    protected $fillable = [
        'limo_pickup_event_id',
        'path',
        'type',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(LimoPickupEvent::class, 'limo_pickup_event_id');
    }
}

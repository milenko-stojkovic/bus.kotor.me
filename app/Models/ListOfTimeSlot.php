<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Termin (time slot) – jedan red u listi termina (npr. "08:00 - 08:20").
 * DailyParkingData povezuje datum + ovaj termin sa kapacitetom; koristi se za validaciju dostupnih termina.
 */
class ListOfTimeSlot extends Model
{
    public $timestamps = false;

    protected $table = 'list_of_time_slots';

    protected $fillable = [
        'time_slot',
    ];

    /**
     * Vraća početak termina za dati datum (parsira "HH:MM" iz time_slot, npr. "08:00 - 08:20" → 08:00).
     */
    public function getStartTimeForDate(Carbon $date): ?Carbon
    {
        $parts = explode(' - ', $this->time_slot, 2);
        $start = trim($parts[0] ?? '');
        if ($start === '') {
            return null;
        }
        try {
            return $date->copy()->setTimeFromTimeString($start);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Vraća kraj termina za dati datum (parsira "HH:MM" iz time_slot, npr. "08:00 - 08:20" → 08:20).
     * "24:00" = ponoć sljedećeg kalendarskog dana (npr. "20:00 - 24:00" traje do kraja dana).
     */
    public function getEndTimeForDate(Carbon $date): ?Carbon
    {
        $parts = explode(' - ', $this->time_slot, 2);
        $end = trim($parts[1] ?? '');
        if ($end === '') {
            return null;
        }
        if (preg_match('/^24:00(:00)?$/', $end) === 1) {
            return $date->copy()->addDay()->startOfDay();
        }
        try {
            return $date->copy()->setTimeFromTimeString($end);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Control / operativni pregled: prikaži termin ako je sada između (početak − N sati) i kraja termina.
     * Tako ostaje vidljiv cio interval (npr. 20:00–24:00 do ponoći), a sutrašnji 00:00–07:00 od 21:00.
     *
     * @param  Carbon  $reservationDay  Početak kalendarskog dana rezervacije (ista TZ kao $now).
     */
    public function isInArrivalControlWindow(Carbon $now, Carbon $reservationDay, int $hoursBeforeStart = 3): bool
    {
        $start = $this->getStartTimeForDate($reservationDay);
        $end = $this->getEndTimeForDate($reservationDay);
        if ($start === null || $end === null) {
            return false;
        }

        $visibleFrom = $start->copy()->subHours($hoursBeforeStart);

        return $now->lt($end) && $now->gte($visibleFrom);
    }

    /** Dnevni podaci o kapacitetu po ovom terminu (za validaciju). */
    public function dailyParkingData(): HasMany
    {
        return $this->hasMany(DailyParkingData::class, 'time_slot_id');
    }

    public function reservationsAsDropOff(): HasMany
    {
        return $this->hasMany(Reservation::class, 'drop_off_time_slot_id');
    }

    public function reservationsAsPickUp(): HasMany
    {
        return $this->hasMany(Reservation::class, 'pick_up_time_slot_id');
    }

    public function tempDataAsDropOff(): HasMany
    {
        return $this->hasMany(TempData::class, 'drop_off_time_slot_id');
    }

    public function tempDataAsPickUp(): HasMany
    {
        return $this->hasMany(TempData::class, 'pick_up_time_slot_id');
    }
}

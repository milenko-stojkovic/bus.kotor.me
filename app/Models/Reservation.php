<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Rezervacija može biti autentifikovana (user_id set) ili guest (user_id null).
 *
 * Snapshot polja (podaci korisnika/vozila u trenutku rezervacije; ostaju i ako se kasnije promene):
 * user_name, country, license_plate, vehicle_type_id, email.
 *
 * email vs users.email: Ostaje duplirano. Guest nema user pa mora imati email ovde; user može kasnije promeniti
 * email u profilu – rezervacija mora imati email kakav je bio u trenutku kupovine. Nemoj uklanjati.
 *
 * vehicle_type_id vs vehicle_id: Oba polja su obavezna. Nemoj brisati vehicle_type_id čak ni kad postoji vehicle_id.
 * vehicle_type_id = snapshot tipa vozila u trenutku rezervacije (istorijski tačno ako user kasnije promeni vozilo).
 * vehicle_id = opcioni link na Vehicle; dupliranje sa vehicle_type_id je namerno.
 *
 * FK: user_id (nullable), vehicle_id (nullable). Relacije: belongsTo(User), belongsTo(Vehicle) sa withDefault();
 * vehicleType() preko snapshot vehicle_type_id.
 */
class Reservation extends Model
{
    /** Fillable: FK, termini, datum, snapshot polja, fiscal, status (za masovno punjenje). */
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'merchant_transaction_id',
        'drop_off_time_slot_id',
        'pick_up_time_slot_id',
        'reservation_date',
        'user_name',
        'country',
        'license_plate',
        'vehicle_type_id',
        'email',
        'fiscal_jir',
        'fiscal_ikof',
        'fiscal_qr',
        'fiscal_operator',
        'fiscal_date',
        'status',
        'email_sent',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'fiscal_date' => 'datetime',
            'email_sent' => 'integer', // 0 = nije poslat, 1 = poslata potvrda (integacija sa ReportEmail / notifikacije)
        ];
    }

    /** Označi da je potvrda rezervacije (email) poslata – za integraciju sa slanjem obaveštenja. */
    public function markConfirmationEmailSent(): bool
    {
        return $this->update(['email_sent' => 1]);
    }

    /**
     * Scope: rezervacije čiji drop-off termin pada u naredna 3 sata od sada.
     */
    public function scopeNextThreeHours(Builder $query): void
    {
        $now = Carbon::now();
        $end = $now->copy()->addHours(3);
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $slots = ListOfTimeSlot::orderBy('id')->get();
        $todaySlotIds = [];
        $tomorrowSlotIds = [];

        foreach ($slots as $slot) {
            $startToday = $slot->getStartTimeForDate($today);
            $startTomorrow = $slot->getStartTimeForDate($tomorrow);
            if ($startToday && $startToday->between($now, $end)) {
                $todaySlotIds[] = $slot->id;
            }
            if ($startTomorrow && $startTomorrow->lte($end)) {
                $tomorrowSlotIds[] = $slot->id;
            }
        }

        if (empty($todaySlotIds) && empty($tomorrowSlotIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $q) use ($todaySlotIds, $tomorrowSlotIds, $today, $tomorrow) {
            if (! empty($todaySlotIds)) {
                $q->orWhere(fn (Builder $b) => $b->whereDate('reservation_date', $today)->whereIn('drop_off_time_slot_id', $todaySlotIds));
            }
            if (! empty($tomorrowSlotIds)) {
                $q->orWhere(fn (Builder $b) => $b->whereDate('reservation_date', $tomorrow)->whereIn('drop_off_time_slot_id', $tomorrowSlotIds));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /** Da li je rezervacija od strane gosta (bez naloga). */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withDefault();
    }

    /**
     * Snapshot podaci za prikaz/forme (guest ili user): user_name, country, license_plate, email.
     * Za guest uvek iz ovih polja; za autentifikovanog takođe sačuvano u rezervaciji.
     */
    public function getSnapshotData(): array
    {
        return [
            'user_name' => $this->user_name,
            'country' => $this->country,
            'license_plate' => $this->license_plate,
            'email' => $this->email,
        ];
    }

    public function dropOffTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'drop_off_time_slot_id');
    }

    public function pickUpTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'pick_up_time_slot_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function postFiscalizationData(): HasOne
    {
        return $this->hasOne(PostFiscalizationData::class, 'reservation_id');
    }
}

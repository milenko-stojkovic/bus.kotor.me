<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privremeni podaci pre rezervacije/plaćanja – soft lock dok se ne upiše rezervacija u reservations.
 *
 * Workflow: plaćanje kreće → upis ovde (user_id NULL ili id); uspeh → čitanje, upis u reservations
 * (prepis user_id + snapshot), brisanje ovog sloga. V. docs/workflow-placanje-temp-data.md.
 *
 * FK: user_id (nullable), drop_off/pick_up_time_slot_id, vehicle_type_id.
 * Status: pending | failed | late_success | processed. Audit trail – nikad fizički brisati temp_data.
 * processed = uspešno plaćanje (rezervacija kreirana); failed = CANCEL/ERROR ili neuspeh.
 */
class TempData extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_LATE_SUCCESS = 'late_success';
    public const STATUS_PROCESSED = 'processed';

    protected $table = 'temp_data';

    protected $fillable = [
        'merchant_transaction_id',
        'user_id',
        'drop_off_time_slot_id',
        'pick_up_time_slot_id',
        'reservation_date',
        'user_name',
        'country',
        'license_plate',
        'vehicle_type_id',
        'email',
        'status',
        'raw_callback_payload',
        'callback_error_code',
        'callback_error_reason',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'raw_callback_payload' => 'array',
        ];
    }

    /** Da li je status finalan (ne obrađivati ponovo). */
    public function isFinalStatus(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_PROCESSED], true);
    }

    /** FK ka User (nullable za guest). withDefault() da pristup user ne baca za null. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /** Da li je unos od strane gosta (bez naloga). */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Snapshot podaci za prikaz/forme (guest ili user): user_name, country, license_plate, email.
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

    /** FK ka TimeSlot (list_of_time_slots) – drop-off termin. */
    public function dropOffTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'drop_off_time_slot_id');
    }

    /** FK ka TimeSlot (list_of_time_slots) – pick-up termin. */
    public function pickUpTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ListOfTimeSlot::class, 'pick_up_time_slot_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
}

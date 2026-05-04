<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LimoPickupEvent extends Model
{
    protected $fillable = [
        'merchant_transaction_id',
        'agency_user_id',
        'agency_name_snapshot',
        'agency_email_snapshot',
        'agency_country_snapshot',
        'source',
        'qr_token_hash',
        'qr_valid_on',
        'vehicle_id',
        'license_plate_snapshot',
        'amount_snapshot',
        'service_name_snapshot',
        'occurred_at',
        'gps_lat',
        'gps_lng',
        'recorded_by_limo_admin_id',
        'device_info',
        'status',
        'fiscal_jir',
        'fiscal_ikof',
        'fiscal_qr',
        'fiscal_operator',
        'fiscal_date',
        'email_sent',
        'invoice_email_sent_at',
    ];

    public const EMAIL_NOT_SENT = 0;

    public const EMAIL_SENDING = 1;

    public const EMAIL_SENT = 2;

    protected function casts(): array
    {
        return [
            'qr_valid_on' => 'date',
            'occurred_at' => 'datetime',
            'fiscal_date' => 'datetime',
            'invoice_email_sent_at' => 'datetime',
            'email_sent' => 'integer',
            'amount_snapshot' => 'decimal:2',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(LimoPickupPhoto::class, 'limo_pickup_event_id');
    }

    public function markInvoiceEmailSent(): bool
    {
        if ($this->invoice_email_sent_at !== null) {
            return true;
        }

        return $this->update([
            'email_sent' => self::EMAIL_SENT,
            'invoice_email_sent_at' => now(),
        ]);
    }
}

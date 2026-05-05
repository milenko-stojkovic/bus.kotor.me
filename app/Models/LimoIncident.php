<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimoIncident extends Model
{
    public const TYPE_QR_INSUFFICIENT_FUNDS = 'qr_insufficient_funds';

    public const TYPE_PLATE_INSUFFICIENT_FUNDS = 'plate_insufficient_funds';

    public const TYPE_UNREGISTERED_VEHICLE_WITH_BRANDING = 'unregistered_vehicle_with_branding';

    public const TYPE_INVALID_QR_TOKEN = 'invalid_qr_token';

    public const TYPE_DRIVER_NON_COOPERATIVE = 'driver_non_cooperative';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_QR_INSUFFICIENT_FUNDS,
        self::TYPE_PLATE_INSUFFICIENT_FUNDS,
        self::TYPE_UNREGISTERED_VEHICLE_WITH_BRANDING,
        self::TYPE_INVALID_QR_TOKEN,
        self::TYPE_DRIVER_NON_COOPERATIVE,
    ];

    protected $table = 'limo_incidents';

    protected $fillable = [
        'incident_uuid',
        'type',
        'license_plate_snapshot',
        'agency_user_id',
        'agency_name_snapshot',
        'agency_email_snapshot',
        'visible_agency_name',
        'plate_photo_path',
        'branding_photo_path',
        'note',
        'occurred_at',
        'gps_lat',
        'gps_lng',
        'recorded_by_limo_admin_id',
        'device_info',
        'communal_email_sent_at',
        'admin_alert_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'communal_email_sent_at' => 'datetime',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_limo_admin_id');
    }

    public function adminAlert(): BelongsTo
    {
        return $this->belongsTo(AdminAlert::class, 'admin_alert_id');
    }
}

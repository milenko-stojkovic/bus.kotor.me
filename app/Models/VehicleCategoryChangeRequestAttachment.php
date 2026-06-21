<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class VehicleCategoryChangeRequestAttachment extends Model
{
    protected $table = 'vehicle_category_change_request_attachments';

    protected $fillable = [
        'vehicle_category_change_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'archived_at',
        'archive_provider',
        'archive_path',
        'archive_error',
        'local_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_category_change_request_id' => 'integer',
            'size' => 'integer',
            'archived_at' => 'datetime',
            'local_deleted_at' => 'datetime',
        ];
    }

    public function hasLocalFile(): bool
    {
        $path = (string) $this->path;

        return $path !== ''
            && $this->local_deleted_at === null
            && Storage::disk($this->disk ?: 'local')->exists($path);
    }

    public function adminArchiveStatusLabel(): string
    {
        if ($this->archived_at !== null && $this->archive_path) {
            return $this->local_deleted_at !== null
                ? 'Arhivirano na MEGA'
                : 'Arhivirano na MEGA (lokalna kopija još postoji)';
        }

        if ($this->archive_error !== null && $this->archive_error !== '') {
            return 'Arhiva neuspješna — potreban retry';
        }

        return 'Lokalni dokument dostupan';
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(VehicleCategoryChangeRequest::class, 'vehicle_category_change_request_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimoQrToken extends Model
{
    protected $fillable = [
        'agency_user_id',
        'token_hash',
        'valid_on',
    ];

    protected function casts(): array
    {
        return [
            'valid_on' => 'date',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimoQrToken extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = [
        'encrypted_token',
    ];

    protected $fillable = [
        'agency_user_id',
        'token_hash',
        'encrypted_token',
        'valid_on',
    ];

    protected function casts(): array
    {
        return [
            'valid_on' => 'date',
        ];
    }
}

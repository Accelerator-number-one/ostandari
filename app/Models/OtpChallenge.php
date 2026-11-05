<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'registration_terms' => 'array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSubscription extends Model
{
    protected $guarded = [];
    protected $hidden = ['mobile', 'mobile_hash', 'code_hash', 'challenge_id'];

    protected function casts(): array
    {
        return ['mobile' => 'encrypted', 'code_expires_at' => 'datetime', 'verified_at' => 'datetime',
            'consented_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'is_active' => 'boolean'];
    }
}

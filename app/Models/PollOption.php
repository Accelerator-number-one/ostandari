<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}

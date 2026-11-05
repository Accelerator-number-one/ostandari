<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasMedia;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }
}

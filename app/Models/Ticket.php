<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasMedia;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function events()
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function county()
    {
        return $this->belongsTo(County::class)->select(['id', 'name']);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->select(['id', 'name']);
    }
}

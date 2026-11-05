<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }

    public function content()
    {
        return $this->belongsTo(Content::class);
    }
}

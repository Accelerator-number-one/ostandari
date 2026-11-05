<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumTopic extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }

    public function replies()
    {
        return $this->hasMany(ForumReply::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumReply extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }

    public function topic()
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }
}

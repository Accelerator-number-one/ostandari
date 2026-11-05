<?php

namespace App\Models;

class Poll extends PortalModel
{
    public function options()
    {
        return $this->hasMany(PollOption::class)->orderBy('priority')->withCount('votes');
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}

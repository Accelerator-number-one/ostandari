<?php

namespace App\Http\Controllers\Api;

use App\Models\Poll;
use App\Models\PollVote;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController
{
    public function vote(Request $r, int $id)
    {
        $d = $r->validate(['option_id' => 'required|integer']);
        $poll = DB::transaction(function () use ($r, $id, $d) {
            $poll = Poll::lockForUpdate()->findOrFail($id);
            abort_unless($poll->status === 'active' && (! $poll->starts_at || $poll->starts_at->lte(now())) && (! $poll->ends_at || $poll->ends_at->gt(now())), 409, 'نظرسنجی فعال نیست.');
            abort_unless($poll->options()->where('id', $d['option_id'])->exists(), 422, 'گزینه متعلق به این نظرسنجی نیست.');
            abort_if(PollVote::where('poll_id', $id)->where('user_id', $r->user()->id)->exists(), 409, 'قبلاً در این نظرسنجی شرکت کرده‌اید.');
            PollVote::create(['poll_id' => $id, 'poll_option_id' => $d['option_id'], 'user_id' => $r->user()->id]);

            return $poll;
        });

        return Api::ok($this->results($poll), 'رأی ثبت شد.', 201);
    }

    public function result(int $id)
    {
        $poll = Poll::whereIn('status', ['active', 'closed'])->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->findOrFail($id);

        return Api::ok($this->results($poll));
    }

    private function results(Poll $poll): array
    {
        $options = $poll->options()->get();
        $total = $options->sum('votes_count');

        return ['id' => $poll->id, 'question' => $poll->question, 'total_votes' => $total, 'options' => $options->map(fn ($o) => ['id' => $o->id, 'title' => $o->title, 'votes' => $o->votes_count, 'percentage' => $total ? round($o->votes_count / $total * 100, 1) : 0])];
    }
}

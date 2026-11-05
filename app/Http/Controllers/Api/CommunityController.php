<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Subscriber;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Notice;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommunityController
{
    public function comment(Request $r, int $id)
    {
        abort_unless($r->user()?->is_active && $r->user()->person_type === 'citizen', 403, 'برای ثبت دیدگاه وارد حساب شهروندی شوید.');
        $content = Content::visible()->findOrFail($id);
        abort_unless($content->comments_enabled, 409, 'ارسال نظر غیرفعال است.');
        $d = $r->validate(['body' => 'required|string|min:3|max:5000']);

        return Api::ok(Comment::create($d + ['content_id' => $id, 'user_id' => $r->user()->id]), 'نظر برای بررسی ثبت شد.', 201);
    }

    public function topics(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'category_id' => 'sometimes|integer']);
        $q = ForumTopic::whereIn('status', ['approved', 'closed'])->with('author')->whereHas('author')->withCount(['replies' => fn ($q) => $q->where('status', 'approved')]);
        if ($r->filled('category_id')) {
            $q->where('category_id', $r->integer('category_id'));
        }

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function topic(int $id)
    {
        return Api::ok(ForumTopic::whereIn('status', ['approved', 'closed'])->with(['author', 'replies' => fn ($q) => $q->where('status', 'approved')->with('author')->limit(200)])->findOrFail($id));
    }

    public function createTopic(Request $r)
    {
        $d = $r->validate(['category_id' => ['required', Rule::exists('categories', 'id')->where('type', 'forum')->where('is_active', true)->whereNull('deleted_at')], 'title' => 'required|string|max:255', 'body' => 'required|string|min:10|max:20000']);

        return Api::ok(ForumTopic::create($d + ['user_id' => $r->user()->id]), 'موضوع برای تأیید ثبت شد.', 201);
    }

    public function reply(Request $r, int $id)
    {
        $topic = ForumTopic::where('status', 'approved')->findOrFail($id);
        $d = $r->validate(['body' => 'required|string|min:3|max:10000']);

        return Api::ok(ForumReply::create($d + ['forum_topic_id' => $topic->id, 'user_id' => $r->user()->id]), 'پاسخ برای تأیید ثبت شد.', 201);
    }

    public function moderateList(Request $r, string $kind)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'status' => 'sometimes|in:pending,approved,rejected,closed', 'q' => 'sometimes|string|max:100']);
        $class = match ($kind) {
            'comments' => Comment::class,'forum-topics' => ForumTopic::class,'forum-replies' => ForumReply::class,default => abort(404)
        };
        $permission = $kind === 'comments' ? 'comments.manage' : 'forum.manage';
        abort_unless($r->user()->hasPermission($permission), 403);
        $q = $class::with('author');
        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }
        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $q->where(function ($query) use ($term, $kind) {
                $query->where('body', 'like', $term)
                    ->orWhereHas('author', fn ($author) => $author->where('name', 'like', $term));
                if ($kind === 'forum-topics') {
                    $query->orWhere('title', 'like', $term);
                }
            });
        }
        $this->moderationScope($q, $r, $kind);

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function moderationShow(Request $r, string $kind, int $id)
    {
        $class = match ($kind) {
            'comments' => Comment::class,
            'forum-topics' => ForumTopic::class,
            'forum-replies' => ForumReply::class,
            default => abort(404),
        };
        abort_unless($r->user()->hasPermission($kind === 'comments' ? 'comments.manage' : 'forum.manage'), 403);
        $query = $class::query()->with('author');
        $this->moderationScope($query, $r, $kind);

        return Api::ok($query->findOrFail($id));
    }

    public function moderate(Request $r, string $kind, int $id)
    {
        $class = match ($kind) {
            'comments' => Comment::class,'forum-topics' => ForumTopic::class,'forum-replies' => ForumReply::class,default => abort(404)
        };
        abort_unless($r->user()->hasPermission($kind === 'comments' ? 'comments.manage' : 'forum.manage'), 403);
        $allowed = $kind === 'forum-topics' ? 'pending,approved,rejected,closed' : 'pending,approved,rejected';
        $d = $r->validate(['status' => 'required|in:'.$allowed, 'reply' => 'sometimes|nullable|string|max:5000']);
        $query = $class::query();
        $this->moderationScope($query, $r, $kind);
        $model = $query->findOrFail($id);
        $model->update($kind === 'comments' ? $d : ['status' => $d['status']]);
        Notice::send($model->user_id, 'نتیجه بررسی محتوای شما مشخص شد.', ['kind' => $kind, 'id' => $id, 'status' => $d['status']]);
        Audit::record($kind.'.moderated', $model, $d);

        return Api::ok($model);
    }

    public function subscribe(Request $r)
    {
        $d = $r->validate(['email' => 'required|email|max:255', 'name' => 'nullable|string|max:120']);
        $subscriber = Subscriber::where('email', $d['email'])->first();
        $out = [];
        if (! $subscriber || ! $subscriber->is_active) {
            $token = Str::random(64);
            $subscriber = Subscriber::updateOrCreate(['email' => $d['email']], ['name' => $d['name'] ?? null, 'token_hash' => hash('sha256', $token), 'verified_at' => null, 'is_active' => false]);
            Mail::raw("Newsletter confirmation token: {$token}\nUse POST /api/v1/newsletter/confirm with email and token.", fn ($m) => $m->to($subscriber->email)->subject('Ostandari newsletter confirmation'));
            if (app()->environment(['local', 'testing']) && config('mail.default') === 'log') {
                $out['dev_token'] = $token;
            }
        }

        return Api::ok($out, 'راهنمای عضویت برای ایمیل ثبت‌شده آماده شد.', 202);
    }

    public function confirm(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'token' => 'required|string|size:64']);
        $s = Subscriber::where('email', $d['email'])->where('token_hash', hash('sha256', $d['token']))->where('updated_at', '>=', now()->subDay())->firstOrFail();
        $s->update(['verified_at' => now(), 'is_active' => true]);

        return Api::ok(null, 'عضویت تأیید شد.');
    }

    public function unsubscribe(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'token' => 'required|string|size:64']);
        $s = Subscriber::where('email', $d['email'])->where('token_hash', hash('sha256', $d['token']))->firstOrFail();
        $s->update(['is_active' => false]);

        return response()->noContent();
    }

    public function subscribers(Request $r)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        abort_unless($r->user()->hasGlobalAccess(), 403);
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'q' => 'sometimes|string|max:100']);
        $query = Subscriber::query();
        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $query->where(fn ($subscriber) => $subscriber->where('email', 'like', $term)->orWhere('name', 'like', $term));
        }

        return Api::page($query->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function showSubscriber(Request $r, int $id)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        abort_unless($r->user()->hasGlobalAccess(), 403);

        return Api::ok(Subscriber::findOrFail($id));
    }

    public function updateSubscriber(Request $r, int $id)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        abort_unless($r->user()->hasGlobalAccess(), 403);
        $s = Subscriber::findOrFail($id);
        $d = $r->validate(['is_active' => 'required|boolean']);
        abort_if($d['is_active'] && ! $s->verified_at, 422, 'عضویت ایمیل تأیید نشده است.');
        $s->update($d);
        Audit::record('subscriber.updated', $s, $d);

        return Api::ok($s);
    }

    public function deleteSubscriber(Request $r, int $id)
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        abort_unless($r->user()->hasGlobalAccess(), 403);
        $s = Subscriber::findOrFail($id);
        $s->delete();
        Audit::record('subscriber.deleted');

        return response()->noContent();
    }

    private function moderationScope($query, Request $r, string $kind): void
    {
        if ($r->user()->hasGlobalAccess()) {
            return;
        }
        if ($kind === 'comments') {
            $query->whereIn('content_id', Access::scope(Content::query(), $r->user())->select('id'));
        } else {
            $categories = Access::scope(Category::query(), $r->user())->select('id');
            if ($kind === 'forum-topics') {
                $query->whereIn('category_id',$categories);
            } else {
                $query->whereIn('forum_topic_id',ForumTopic::whereIn('category_id',$categories)->select('id'));
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Content;
use App\Models\Gallery;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Service;
use App\Services\Access;
use App\Services\Audit;
use App\Services\FileAccess;
use App\Services\InlineMedia;
use App\Services\CurrentGovernor;
use App\Services\ResourcePayload;
use App\Services\ResourceRegistry;
use App\Support\Api;
use App\Support\QueryInput;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceController
{
    private function query(Request $request, string $resource)
    {
        $def = ResourceRegistry::get($resource);
        $user = $request->user();
        abort_unless($user->isStaff() && $user->hasPermission($def['permission']), 403);
        if (! $request->isMethodSafe() && ! $user->hasGlobalAccess()) {
            abort_if($resource === 'counties' || $resource === 'organizations' && ! in_array($request->method(), ['PUT', 'PATCH'], true), 403, 'ساختار استان و فرمانداری‌ها فقط در مدیریت مرکزی تغییر می‌کند.');
        }
        $q = $def['model']::query()->with($def['with'] ?? []);
        foreach ($def['scope'] ?? [] as $k => $v) {
            $q->where($k, $v);
        }
        Access::scope($q, $user, $def['tenant'] ?? false);
        if ($resource === 'organizations' && ! $user->hasGlobalAccess()) {
            $q->whereIn('id', Access::organizations($user));
        }
        if ($resource === 'gallery-items' && ! $user->hasGlobalAccess()) {
            $q->whereIn('gallery_id', Access::scope(Gallery::query(), $user)->select('id'));
        }

        return $q;
    }

    public function index(Request $r, string $resource)
    {
        $def = ResourceRegistry::get($resource);
        $q = $this->query($r, $resource);
        $r->validate(['q' => 'sometimes|string|max:200', 'per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1', 'trashed' => 'sometimes|boolean']);
        QueryInput::filters($r, $def);
        if ($r->boolean('trashed') && in_array(SoftDeletes::class, class_uses_recursive($def['model']), true)) {
            $q->onlyTrashed();
        }
        foreach ($def['filters'] ?? [] as $key) {
            if ($r->has($key)) {
                $q->where($key, $r->query($key));
            }
        }
        if ($r->filled('q')) {
            $q->where(function ($q) use ($def, $r) {
                foreach ($def['search'] as $field) {
                    $q->orWhere($field, 'like', '%'.$r->string('q').'%');
                }
            });
        }

        if ($resource === 'members' && $r->query('kind') === 'governor') \App\Services\GovernorTerms::order($q);
        return Api::page($q->orderByDesc('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function show(Request $r, string $resource, int $id)
    {
        return Api::ok($this->query($r, $resource)->findOrFail($id));
    }

    public function store(Request $r, string $resource, ResourcePayload $payload)
    {
        $this->query($r, $resource);
        $def = ResourceRegistry::get($resource);
        $data = $payload->validate($resource, $r->all(), $r->user());
        $model = $this->transaction(function () use ($r, $data, $def, $resource) {
            $model = $def['model']::create(Arr::except($data, ['tag_ids', 'media_ids']));
            $this->sync($model, $data, $r);
            Audit::record($resource.'.created', $model, $data);

            return $model;
        });

        return Api::ok($model->refresh()->load($def['with'] ?? []), 'رکورد ایجاد شد.', 201);
    }

    public function update(Request $r, string $resource, int $id, ResourcePayload $payload)
    {
        $model = $this->query($r, $resource)->findOrFail($id);
        $data = $payload->validate($resource, $r->all(), $r->user(), $model);
        $this->transaction(function () use ($model, $data, $r, $resource) {
            $model->update(Arr::except($data, ['tag_ids', 'media_ids']));
            $this->sync($model, $data, $r);
            Audit::record($resource.'.updated', $model, $data);
        });

        return Api::ok($model->refresh()->load(ResourceRegistry::get($resource)['with'] ?? []));
    }

    private function sync($model, array $data, Request $r): void
    {
        if (array_key_exists('tag_ids', $data)) {
            $model->tags()->sync($data['tag_ids']);
        }
        if (array_key_exists('media_ids', $data)) {
            FileAccess::attach($model, $data['media_ids'], $r->user(), 'public');
        }
        InlineMedia::sync($model, $r->user());
    }

    private function transaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'members_one_current_governor')) {
                throw ValidationException::withMessages(['is_current' => 'استاندار فعلی قبلاً ثبت شده است. همان رکورد را ویرایش کنید.']);
            }
            throw $exception;
        }
    }

    public function destroy(Request $r, string $resource, int $id)
    {
        $model = $this->query($r, $resource)->findOrFail($id);
        $this->assertDeletable($model);
        DB::transaction(function () use ($model, $resource) {
            Audit::record($resource.'.deleted', $model);
            $model->delete();
        });

        return response()->noContent();
    }

    public function restore(Request $r, string $resource, int $id)
    {
        $q = $this->query($r, $resource);
        abort_unless(in_array(SoftDeletes::class, class_uses_recursive($q->getModel()), true), 405);
        $model = $q->onlyTrashed()->findOrFail($id);
        foreach (['parent_id', 'category_id', 'organization_id', 'menu_id', 'gallery_id'] as $fk) {
            if ($model->$fk) {
                $table = ['parent_id' => $model->getTable(), 'category_id' => 'categories', 'organization_id' => 'organizations', 'menu_id' => 'menus', 'gallery_id' => 'galleries'][$fk];
                abort_if(DB::table($table)->where('id', $model->$fk)->whereNotNull('deleted_at')->exists(), 409, 'ابتدا رکورد والد را بازیابی کنید.');
            }
        }
        if ($model instanceof Member) {
            CurrentGovernor::validate([], $r->user(), $model);
        }
        $this->transaction(fn () => $model->restore());
        Audit::record($resource.'.restored', $model);

        return Api::ok($model);
    }

    public function reorder(Request $r, string $resource)
    {
        $q = $this->query($r, $resource);
        abort_unless(isset(ResourceRegistry::get($resource)['rules']['priority']), 405);
        $data = $r->validate(['items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer|distinct', 'items.*.priority' => 'required|integer|min:0|max:100000']);
        $ids = array_column($data['items'], 'id');
        abort_unless((clone $q)->whereIn('id', $ids)->count() === count($ids), 404);
        DB::transaction(function () use ($data, $q) {
            foreach ($data['items'] as $item) {
                (clone $q)->where('id', $item['id'])->update(['priority' => $item['priority']]);
            }
        });
        Audit::record($resource.'.reordered');

        return Api::ok();
    }

    private function assertDeletable($model): void
    {
        if ($model instanceof PollOption) {
            $poll = Poll::findOrFail($model->poll_id);
            abort_if($poll->votes()->exists() || $poll->status === 'active' && $poll->options()->count() <= 2, 409, 'گزینه‌های نظرسنجی فعال یا دارای رأی قابل حذف نیستند.');
        }
        $dependencies = match (true) {
            $model instanceof Category => ['categories' => 'parent_id', 'contents' => 'category_id', 'slides' => 'category_id', 'members' => 'category_id', 'services' => 'category_id', 'links' => 'category_id', 'galleries' => 'category_id', 'dynamic_forms' => 'category_id', 'forum_topics' => 'category_id'],
            $model instanceof Organization => ['organizations' => 'parent_id', 'users' => 'organization_id', 'categories' => 'organization_id', 'contents' => 'organization_id', 'members' => 'organization_id', 'services' => 'organization_id', 'galleries' => 'organization_id', 'dynamic_forms' => 'organization_id', 'tickets' => 'organization_id'],
            $model instanceof Menu => ['menu_items' => 'menu_id'], $model instanceof MenuItem => ['menu_items' => 'parent_id'],
            $model instanceof Service => ['services' => 'parent_id'], $model instanceof Gallery => ['gallery_items' => 'gallery_id'],
            $model instanceof Content => ['menu_items' => 'content_id', 'slides' => 'content_id', 'comments' => 'content_id'],
            $model instanceof PollOption => ['poll_votes' => 'poll_option_id'], default => [],
        };
        foreach ($dependencies as $table => $fk) {
            $q = DB::table($table)->where($fk,$model->id);
            if (in_array($table,['categories', 'contents', 'slides', 'members', 'services', 'links', 'galleries', 'dynamic_forms', 'menu_items', 'organizations', 'gallery_items'],true)) {
                $q->whereNull('deleted_at');
            }
            abort_if($q->exists(),409,'ابتدا رکوردهای وابسته را جابه‌جا یا حذف کنید.');
        }
    }
}

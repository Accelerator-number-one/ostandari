<?php

namespace App\Services;

use App\Models\Content;
use App\Models\DynamicForm;
use App\Models\Member;
use App\Models\Media;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResourcePayload
{
    public function validate(string $resource, array $input, User $actor, ?Model $model = null): array
    {
        $def = ResourceRegistry::get($resource);
        // A blank optional relation is an empty selection, not an ID of zero.
        // Omitted fields stay omitted so partial updates preserve existing links.
        foreach (['tag_ids', 'media_ids'] as $field) {
            if (! isset($def['rules'][$field]) || ! array_key_exists($field, $input)) {
                continue;
            }
            if ($input[$field] === null || $input[$field] === '') {
                $input[$field] = [];
            } elseif (is_array($input[$field])) {
                $input[$field] = array_values(array_filter($input[$field], fn ($id) => $id !== null && (! is_string($id) || trim($id) !== '')));
            }
        }
        $creating = ! $model;
        $class = $def['model'];
        $sample = $model ?? new $class;
        if ($sample instanceof Member) foreach (['term_start_year', 'term_end_year'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $value = trim(GovernorTerms::digits($input[$field]));
                $input[$field] = $value === '' ? null : $value;
            }
        }
        if ($creating && ($def['tenant'] ?? false)) $input['organization_id'] ??= $actor->organization_id;
        if ($creating && $sample instanceof Member && ! $actor->hasGlobalAccess() && $actor->organization?->county_id) $input['county_id'] ??= $actor->organization->county_id;
        foreach ($def['scope'] ?? [] as $key => $value) {
            if (isset($input[$key]) && $input[$key] !== $value) {
                throw ValidationException::withMessages([$key => 'نوع منبع قابل تغییر نیست.']);
            }
            $input[$key] = $value;
        }
        $rules = $def['rules'];
        if ($creating && isset($rules['locale'])) {
            $input['locale'] ??= 'fa';
        }
        if ($creating && isset($rules['slug']) && empty($input['slug'])) {
            $base = trim(preg_replace('/[^\pL\pN]+/u', '-', mb_strtolower($input['title'] ?? $input['name'] ?? 'item')), '-') ?: Str::lower(Str::ulid());
            $input['slug'] = mb_substr($base, 0, 180);
            $i = 1;
            while ($this->uniqueQuery($sample, $input)->where('slug', $input['slug'])->exists()) {
                $input['slug'] = mb_substr($base, 0, 170).'-'.(++$i);
            }
        }
        if (isset($rules['slug'])) {
            $unique = Rule::unique($sample->getTable(), 'slug')->ignore($model?->id);
            foreach (['locale', 'type'] as $column) {
                if (array_key_exists($column, $rules)) {
                    $unique->where($column, $input[$column] ?? $model?->$column ?? 'fa');
                }
            }
            if ($resource === 'tags') $unique->where('organization_id', $input['organization_id'] ?? $model?->organization_id ?? $actor->organization_id);
            $rules['slug'] = ['required', 'string', 'max:255', 'regex:/^[\pL\pN_-]+$/u', $unique];
        }
        if ($resource === 'menus') {
            $rules['key'][] = Rule::unique('menus', 'key')->where('locale', $input['locale'] ?? $model?->locale ?? 'fa')->where('organization_id', $input['organization_id'] ?? $model?->organization_id ?? $actor->organization_id)->ignore($model?->id);
        }
        if (! $creating) {
            foreach ($rules as $key => &$rule) {
                if (! str_contains($key, '.')) {
                    $rule = array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule);
                }
            }
        }
        unset($rule);
        $data = Validator::make($input, $rules)->validate();
        $data = array_merge($data, $def['scope'] ?? []);
        if (($def['tenant'] ?? false)) {
            if ($creating && ! isset($data['organization_id']) && $actor->organization_id) {
                $data['organization_id'] = $actor->organization_id;
            }
            Access::organization($actor, $data['organization_id'] ?? $model?->organization_id);
        }
        if ($resource === 'organizations') {
            if (isset($data['parent_id']) && (! $model || (int) $data['parent_id'] !== (int) $model->parent_id)) {
                Access::organization($actor, $data['parent_id']);
            }
            abort_if($creating && ! $actor->hasGlobalAccess() && empty($data['parent_id']), 403, 'واحد جدید باید زیرمجموعهٔ استان یا فرمانداری شما باشد.');
        }
        if ($resource === 'gallery-items') {
            Access::gallery($actor, (int) ($data['gallery_id'] ?? $model?->gallery_id));
        }
        $effective = array_merge($model?->getAttributes() ?? [], $data);
        if ($sample instanceof Slide && empty($effective['media_id']) && trim((string) ($effective['title'] ?? '')) === '') {
            throw ValidationException::withMessages(['media_id' => 'برای اسلاید فقط عکس، تصویر را بارگذاری کنید. اسلاید بدون تصویر و عنوان قابل ذخیره نیست.']);
        }
        foreach (['category_id' => \App\Models\Category::class, 'menu_id' => \App\Models\Menu::class, 'poll_id' => Poll::class, 'content_id' => Content::class] as $field => $relatedClass) {
            if (! empty($effective[$field])) Access::organization($actor, $relatedClass::findOrFail($effective[$field])->organization_id);
        }
        foreach (\App\Models\Tag::whereIn('id', $data['tag_ids'] ?? [])->get() as $tag) Access::organization($actor, $tag->organization_id);
        if (! $actor->hasGlobalAccess() && $sample instanceof \App\Models\Organization) {
            foreach (['parent_id', 'county_id', 'slug', 'type', 'is_active'] as $field) {
                abort_if(array_key_exists($field, $data) && (string) $data[$field] !== (string) $model?->$field, 403, 'ساختار و شناسهٔ فرمانداری را از این پنل تغییر ندهید.');
            }
        }
        if (! $actor->hasGlobalAccess() && $sample instanceof Member && $actor->organization?->county_id) {
            abort_unless((int) ($effective['county_id'] ?? 0) === (int) $actor->organization->county_id, 403, 'مسئول باید متعلق به شهرستان همین فرمانداری باشد.');
        }
        foreach (['media_id', 'cover_media_id', 'banner_media_id', 'aerial_media_id'] as $field) {
            if (! empty($data[$field])) {
                $file = Media::findOrFail($data[$field]);
                if (! str_starts_with($file->mime, 'image/')) {
                    throw ValidationException::withMessages([$field => 'فایل انتخابی باید تصویر باشد. برای فایل‌های دیگر از پیوست استفاده کنید.']);
                }
                $unchanged = $model && (int) $model->$field === (int) $file->id;
                abort_unless($unchanged || FileAccess::mayManage($file, $actor), 403, 'تصویر متعلق به این حساب نیست.');
            }
        }
        if ($sample instanceof Member) {
            $data = GovernorTerms::payload($data, $model);
            $effective = array_merge($model?->getAttributes() ?? [], $data);
            CurrentGovernor::validate($data, $actor, $model);
        }
        foreach (['starts_at' => 'ends_at', 'published_at' => 'expires_at', 'opens_at' => 'closes_at', 'term_start' => 'term_end'] as $start => $end) {
            if (! empty($effective[$start]) && ! empty($effective[$end]) && strtotime($effective[$end]) <= strtotime($effective[$start])) {
                throw ValidationException::withMessages([$end => 'زمان پایان باید بعد از زمان شروع باشد.']);
            }
        }
        if (isset($rules['parent_id']) && ! empty($effective['parent_id'])) {
            $parent = $class::findOrFail($effective['parent_id']);
            $seen = $model ? [$model->id] : [];
            for ($i = 0; $parent && $i < 32; $i++) {
                if (in_array($parent->id, $seen, true)) {
                    throw ValidationException::withMessages(['parent_id' => 'ایجاد چرخه در ساختار مجاز نیست.']);
                }
                $seen[] = $parent->id;
                $parent = $parent->parent_id ? $class::find($parent->parent_id) : null;
            }
            if ($parent) {
                throw ValidationException::withMessages(['parent_id' => 'عمق ساختار بیش از حد مجاز است.']);
            }
            $parent = $class::findOrFail($effective['parent_id']);
            if ($def['tenant'] ?? false) {
                Access::organization($actor, $parent->organization_id);
            }
            foreach (['menu_id', 'type', 'locale'] as $key) {
                if ($sample instanceof \App\Models\Organization && $key === 'type') continue;
                if (isset($effective[$key]) && $effective[$key] !== $parent->$key) {
                    throw ValidationException::withMessages(['parent_id' => 'والد باید در همان گروه، زبان و نوع باشد.']);
                }
            }
        }
        if ($sample instanceof Content) {
            if (array_key_exists('duty_group', $data['metadata'] ?? [])) {
                Validator::make(['duty_group' => $data['metadata']['duty_group']], ['duty_group' => ['nullable', 'string', 'max:255']])->validate();
                if (($effective['type'] ?? '') !== 'duty' && !empty($data['metadata']['duty_group'])) throw ValidationException::withMessages(['duty_group' => 'گروه واحد فقط برای شرح وظایف قابل انتخاب است.']);
            }
            if (array_key_exists('duty_group', $data)) {
                if (($effective['type'] ?? '') !== 'duty' && !empty($data['duty_group'])) throw ValidationException::withMessages(['duty_group' => 'گروه واحد فقط برای شرح وظایف قابل انتخاب است.']);
                $data['metadata'] = array_replace($model?->metadata ?? [], $data['metadata'] ?? [], ['duty_group' => $data['duty_group'] ?: null]);
                unset($data['duty_group']);
            }
            if (array_key_exists('portal_path', $data) || array_key_exists('portal_path', $data['metadata'] ?? [])) {
                $path = array_key_exists('portal_path', $data) ? $data['portal_path'] : ($data['metadata']['portal_path'] ?? null);
                if ($path) {
                    abort_unless(($effective['type'] ?? '') === 'page' && \App\Models\Organization::whereKey($effective['organization_id'] ?? null)->where('type','county')->exists(), 422, 'محل نمایش فقط برای صفحه‌های همین پرتال فرمانداری است.');
                    Validator::make(['portal_path'=>$path], ['portal_path'=>[Rule::in(array_keys(CountyPortal::pages()))]])->validate();
                    if (Content::where('organization_id',$effective['organization_id'])->where('type','page')->where('locale',$effective['locale']??'fa')
                        ->where('metadata->portal_path',$path)->when($model,fn($q)=>$q->whereKeyNot($model->id))->exists()) {
                        throw ValidationException::withMessages(['portal_path'=>'برای این صفحه قبلاً مطلبی ثبت شده است؛ همان مطلب را ویرایش کنید.']);
                    }
                }
                $data['metadata']=array_replace($model?->metadata??[], $data['metadata']??[], ['portal_path'=>$path]);
                unset($data['portal_path']);
            }
            if (array_key_exists('news_scope', $data)) {
                if (($effective['type'] ?? '') !== 'news' && $data['news_scope'] !== null && $data['news_scope'] !== '') {
                    throw ValidationException::withMessages(['news_scope' => 'گروه اخبار فقط برای خبر قابل انتخاب است.']);
                }
                $data['metadata'] = array_replace($model?->metadata ?? [], $data['metadata'] ?? [], ['news_scope' => $data['news_scope'] ?: null]);
                unset($data['news_scope']);
            }
            if (($effective['type'] ?? '') === 'news' && !$actor->hasGlobalAccess() && $actor->organization?->type === 'county') {
                $data['metadata'] = array_replace($model?->metadata ?? [], $data['metadata'] ?? [], ['news_scope' => 'governorates']);
            }
            if (! empty($effective['show_in_slideshow']) && empty($effective['cover_media_id'])) {
                throw ValidationException::withMessages(['cover_media_id' => 'برای نمایش این مطلب در اسلایدشو، تصویر شاخص را بارگذاری کنید یا تیک نمایش در اسلایدشو را بردارید.']);
            }
            if ($creating) {
                $data['author_id'] = $actor->id;
            }
            if (($effective['status'] ?? 'draft') === 'published' && empty($effective['published_at'])) {
                $data['published_at'] = now();
            }
        }
        if ($resource === 'galleries' && ($effective['status'] ?? 'draft') === 'published' && empty($effective['published_at'])) {
            $data['published_at'] = now();
        }
        if ($sample instanceof Poll && ($effective['status'] ?? 'draft') === 'active' && (! $model || $model->options()->count() < 2)) {
            throw ValidationException::withMessages(['status' => 'نظرسنجی ابتدا به صورت پیش‌نویس با حداقل دو گزینه ساخته شود.']);
        }
        if ($sample instanceof PollOption && $model && $model->votes()->exists()) {
            foreach (['title', 'poll_id'] as $field) {
                if (isset($data[$field]) && (string) $data[$field] !== (string) $model->$field) {
                    abort(409, 'گزینه دارای رأی قابل تغییر نیست.');
                }
            }
        }
        if ($sample instanceof Poll && $model && $model->votes()->exists()) {
            foreach (['question', 'locale'] as $field) {
                if (isset($data[$field]) && $data[$field] !== $model->$field) {
                    abort(409, 'متن نظرسنجی دارای رأی قابل تغییر نیست.');
                }
            }
        }
        if ($sample instanceof PollOption) {
            $pollId = (int) ($data['poll_id'] ?? $model?->poll_id);
            if (PollVote::where('poll_id', $pollId)->exists() && (! $model || isset($data['title']) && $data['title'] !== $model->title || isset($data['poll_id']) && $data['poll_id'] != $model->poll_id)) {
                abort(409, 'گزینه‌های نظرسنجی دارای رأی قابل تغییر نیستند.');
            }
        }
        if ($sample instanceof DynamicForm && isset($data['fields'])) {
            app(FormEngine::class)->validateSchema($data['fields']);
        }
        foreach (['body', 'description', 'answer', 'bio'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = Html::clean($data[$field]);
            }
        }
        if ($sample instanceof Content && array_key_exists('body', $data) && ! trim(html_entity_decode(strip_tags($data['body']), ENT_QUOTES, 'UTF-8')) && ! preg_match('/<(?:img|table)\b/i', $data['body'])) {
            throw ValidationException::withMessages(['body' => 'متن کامل محتوا پس از پاک‌سازی خالی است.']);
        }
        if (isset($data['metadata']) && strlen(json_encode($data['metadata'])) > 16000) {
            throw ValidationException::withMessages(['metadata' => 'فراداده بیش از حد مجاز است.']);
        }

        if ($resource === 'links' && array_key_exists('icon', $data)) {
            if ($data['icon']) {
                $data['media_id'] = PortalIcons::media($data['icon'], $actor, $data['organization_id'] ?? $model?->organization_id ?? $actor->organization_id)->id;
            }
            unset($data['icon']);
        }

        return $data;
    }

    private function uniqueQuery(Model $model, array $input)
    {
        $q = $model->newQueryWithoutScopes();
        if ($model instanceof \App\Models\Tag) $q->where('organization_id', $input['organization_id'] ?? null);
        foreach (['type', 'locale'] as $key) {
            if (isset($input[$key])) {
                $q->where($key,$input[$key]);
            }
        }

        return $q;
    }
}

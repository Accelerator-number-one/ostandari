<?php

namespace App\Services;

use App\Models\{CustomPage, Media, Organization, User};
use App\Support\CountySlugs;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\{Rule, ValidationException};

final class CustomPages
{
    public const PLACEMENTS = ['services' => 'مگامنوی خدمات', 'about' => 'مگامنوی معرفی استان یا شهرستان',
        'quick' => 'دسترسی سریع', 'links' => 'پیوندهای مفید', 'footer' => 'پایین سایت', 'banner' => 'بنر صفحهٔ اصلی'];

    public static function ready(): bool { return Schema::hasTable('custom_pages') && Schema::hasTable('custom_page_images'); }

    public static function mayManage(User $user): bool
    {
        return $user->is_active && $user->isStaff() && $user->hasPermission('content.manage') &&
            ($user->hasGlobalAccess() || in_array($user->organization?->type, ['governorate', 'county'], true));
    }

    public static function organization(User $user): Organization
    {
        Workspace::assertUser($user);
        abort_unless(self::mayManage($user), 403);
        return $user->hasGlobalAccess()
            ? Organization::active()->where('type', 'governorate')->orderBy('id')->firstOrFail()
            : Organization::active()->whereKey($user->organization_id)->firstOrFail();
    }

    public static function module(): array
    {
        return ['path' => '/panel/admin/custom-pages', 'title' => 'ساخت صفحه جدید', 'description' => '',
            'resource' => '', 'permission' => 'content.manage', 'section' => 'content', 'showInNavigation' => true,
            'canCreate' => false, 'canEdit' => true, 'custom_page_builder' => true];
    }

    public static function path(string $value): string { return '/'.trim(mb_strtolower($value), '/'); }

    public static function reserved(string $path): bool
    {
        $first = explode('/', ltrim($path, '/'))[0];
        $meta = app(PortalView::class)->data('frontend');
        $reserved = ['media-assets','staff','staff-admin','staff-assets','api','web-api','session','panel','admin','county-admin','citizen','login','register','en','ar',
            'forgot-password','password','verify','office','county','up','storage','design','styles','build','fonts',
            'services','service','links','gallery','forms','forum','governors','deputies','transparency','governorate',
            'duties','sitemap','rss','about','districts','units','accessibility','performance','participation',
            'service-agreement','polls','announcements','resolutions','documents','section','content','news','page-assets',
            'assets','images','img','css','js','manifest','sw','uploads','media'];
        foreach ([...array_keys($meta['publicTitles']), ...array_column($meta['officialSections'], 'href')] as $route)
            $reserved[] = explode('/', trim(parse_url($route, PHP_URL_PATH) ?: '', '/'))[0];
        foreach ($meta['counties'] as $county) $reserved = [...$reserved, ...CountySlugs::variants($county['slug'])];
        return $path === '/' || in_array($first, $reserved, true) || app(PortalView::class)->county($first) !== null;
    }

    public static function save(User $actor, array $input, ?CustomPage $page = null): CustomPage
    {
        $org = self::organization($actor);
        $legacyPage = $page && !empty($page->legacy_source_key);
        abort_if(array_intersect(array_keys($input), ['organization_id','author_id','deleted_at','id']), 403, 'مالک صفحه از پنل شما تعیین می‌شود.');
        $input = array_replace($page?->only(['title','path','body','link_title','link_style','link_image_media_id','placements','status','priority'])
            ?? ['link_title' => null, 'link_style' => 'text', 'link_image_media_id' => null, 'placements' => [], 'status' => 'draft', 'priority' => 0], $input);
        $data = Validator::make($input, [
            'title' => 'required|string|max:255', 'path' => ['required','string','max:220','regex:~^/?[\pL\pN_-]+(?:/[\pL\pN_-]+)*/?$~u'],
            'body' => ($legacyPage ? 'nullable' : 'required').'|string|max:1000000', 'link_title' => 'nullable|string|max:255',
            'link_style' => ['required', Rule::in(['text','image'])],
            'link_image_media_id' => ['nullable','integer',Rule::exists('media','id')->whereNull('deleted_at')->where('visibility','public')],
            'placements' => 'present|array|max:6', 'placements.*' => ['string','distinct',Rule::in(array_keys(self::PLACEMENTS))],
            'status' => ['required',Rule::in(['draft','published','archived'])], 'priority' => 'sometimes|integer|min:0|max:100000',
        ], ['path.regex' => 'مسیر باید مانند /my-page باشد؛ فاصله، نشانی کامل اینترنتی و پارامتر مجاز نیست.'])->validate();
        $data['path'] = self::path($data['path']);
        if ($legacyPage && $data['path'] !== $page->path)
            throw ValidationException::withMessages(['path' => 'مسیر این صفحه ثابت است تا پیوندهای سایت قطع نشوند.']);
        if (self::reserved($data['path'])) throw ValidationException::withMessages(['path' => 'این مسیر متعلق به بخش‌های فعلی سایت است؛ یک مسیر جدید انتخاب کنید.']);
        if (($data['link_style'] === 'image' || in_array('banner', $data['placements'], true)) && empty($data['link_image_media_id']))
            throw ValidationException::withMessages(['link_image_media_id' => 'برای پیوند تصویری یا بنر، تصویر را انتخاب یا بارگذاری کنید.']);
        if (in_array('banner', $data['placements'], true)) $data['link_style'] = 'image';
        $data['body'] = Html::clean($data['body'] ?? '');
        if (!$legacyPage && trim(strip_tags($data['body'])) === '' && !str_contains($data['body'], '<img '))
            throw ValidationException::withMessages(['body' => 'محتوای صفحه را وارد کنید.']);
        return DB::transaction(function () use ($actor, $org, $data, $page) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            if ($page) $page = CustomPage::where('organization_id', $org->id)->lockForUpdate()->findOrFail($page->id);
            if (CustomPage::withTrashed()->where('organization_id', $org->id)->where('path', $data['path'])
                ->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists())
                throw ValidationException::withMessages(['path' => 'این مسیر قبلاً استفاده شده است؛ صفحهٔ قبلی را ویرایش یا از زباله‌دان بازیابی کنید.']);
            $image = !empty($data['link_image_media_id']) ? Media::lockForUpdate()->findOrFail($data['link_image_media_id']) : null;
            if ($image) abort_unless((int) $image->organization_id === $org->id && str_starts_with($image->mime, 'image/') &&
                $image->visibility === 'public' && FileAccess::mayManage($image, $actor), 403, 'تصویر باید عمومی و متعلق به همین پرتال باشد.');
            $creating = !$page;
            $page ??= new CustomPage(['organization_id' => $org->id, 'author_id' => $actor->id]);
            $page->fill($data)->save();
            InlineMedia::syncHtml($page, $actor, $data['body'].($image ? '<img src="'.e($image->url).'">' : ''));
            Audit::record($creating ? 'custom-pages.created' : 'custom-pages.updated', $page, ['path' => $page->path, 'placements' => $page->placements]);
            return $page->refresh()->load('linkImage');
        });
    }

    public static function published(?int $organizationId): array
    {
        if (!$organizationId || !self::ready()) return [];
        return CustomPage::visible()->where('organization_id', $organizationId)->with('linkImage')->orderBy('priority')->orderBy('id')
            ->get(['id','organization_id','title','path','link_title','link_style','link_image_media_id','placements','priority'])->toArray();
    }

    public static function resolve(string $path, ?array $county): ?CustomPage
    {
        if (!self::ready()) return null;
        $org = Organization::active()->when($county, fn ($q) => $q->where('type','county')->whereHas('county',
            fn ($q) => $q->whereIn('slug', CountySlugs::variants($county['slug']))), fn ($q) => $q->where('type','governorate'))->orderBy('id')->first();
        return $org ? CustomPage::visible()->where('organization_id', $org->id)->where('path', self::path(rawurldecode($path)))->first() : null;
    }
}

<?php

namespace App\Services;

use App\Models\{Category, Content, Link, Member, Organization, Setting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ManagerPages
{
    public const GROUPS = [
        '1' => ['menu' => '61', 'title' => 'مدیران ستادی استانداری مازندران'],
        '2' => ['menu' => '64', 'title' => 'معاونین استاندار مازندران'],
        '3' => ['menu' => '62', 'title' => 'مدیران دستگاه‌های اجرایی استان مازندران'],
        '4' => ['menu' => '63', 'title' => 'فرمانداران مازندران'],
        '5' => ['menu' => '198', 'title' => 'مدیران روابط عمومی استان مازندران'],
    ];
    public const PAGE_SLUG = 'public-relations-manager-directory';
    public const PREPARED_KEY = 'imports.manager-pages.2026-11-05.v1';
    public const PDF_TITLE = 'دانلود لیست اسامی و شماره تماس مدیران روابط عمومی دستگاه های استان مازندران';
    public const PDF_URL = 'https://ostan-mz.s3.ir-thr-at1.arvanstorage.ir/%D8%B1%D9%88%D8%A7%D8%A8%D8%B7%20%D8%B9%D9%85%D9%88%D9%85%DB%8C%20%D8%AF%D8%B3%D8%AA%DA%AF%D8%A7%D9%87%20%D9%87%D8%A7%DB%8C%20%D8%A7%D8%AC%D8%B1%D8%A7%D9%8A%DB%8C%20-%20%D8%AC%D8%AF%D9%8A%D8%AF.pdf?versionId=';

    public static function path(string $group): string
    {
        if (! isset(self::GROUPS[$group])) throw new \InvalidArgumentException('Unknown manager group.');
        return '/managers?group='.$group;
    }

    /** Translate only the five legacy provincial directory destinations. */
    public static function internalHref(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) return null;
        $host = strtolower($parts['host'] ?? '');
        if ($host !== '' && ! in_array($host, ['ostan-mz.ir', 'www.ostan-mz.ir'], true)) return null;
        $path = strtolower(rtrim($parts['path'] ?? '', '/'));
        $menu = null;
        if ($path === '/home/showdetailsmenucontent') {
            parse_str($parts['query'] ?? '', $query);
            if (isset($query['OrgId']) && (! is_scalar($query['OrgId']) || (string) $query['OrgId'] !== '1')) return null;
            $menu = $query['MenuId'] ?? null;
        } elseif (preg_match('~^/section/menu-(\d+)$~', $path, $match)) {
            $menu = $match[1];
        }
        if (! is_string($menu) && ! is_int($menu)) return null;
        foreach (self::GROUPS as $group => $definition) {
            if ((string) $menu === $definition['menu']) return self::path((string) $group);
        }
        return null;
    }

    public static function page(?int $organizationId, bool $publishedOnly = true): ?Content
    {
        if (! $organizationId) return null;
        $query = Content::where('organization_id', $organizationId)->where('type', 'page')->where('locale', 'fa')
            ->where('metadata->manager_directory', 'public-relations');
        if ($publishedOnly) $query->visible();
        return $query->orderBy('id')->first();
    }

    public static function mayManage(User $user): bool
    {
        return $user->is_active && $user->isStaff() && ! $user->isStaffManager()
            && $user->isPublicRelations() && $user->organization?->is_active
            && $user->organization->type === 'governorate';
    }

    public static function ownsPage(User $user, int $id): bool
    {
        return self::mayManage($user) && Content::withTrashed()->whereKey($id)
            ->where('organization_id', $user->organization_id)->where('type', 'page')->where('locale', 'fa')
            ->where('metadata->manager_directory', 'public-relations')->exists();
    }

    /** A bounded exception: provincial PR can edit these directories, not other portals. */
    public static function mayWrite(Request $request): bool
    {
        $user = $request->user();
        if (! $user || ! self::mayManage($user)) return false;
        $resource = $request->route('resource');
        if ($resource === 'members') {
            if (! $user->hasPermission('members.manage')) return false;
            if ($request->has('kind') && ! in_array($request->input('kind'), ['manager', 'county_governor'], true)) return false;
            if ($id = $request->route('id')) {
                return Member::withTrashed()->whereKey($id)->where('organization_id', $user->organization_id)
                    ->whereIn('kind', ['manager', 'county_governor'])->exists();
            }
            return $request->isMethod('POST') && ! $request->has('items');
        }
        if ($resource === 'links') {
            return $user->hasPermission('content.manage');
        }
        if ($resource === 'contents' && $request->route('id') && $user->hasPermission('content.manage')) {
            return self::ownsPage($user, (int) $request->route('id'));
        }
        return $request->route()?->getControllerClass() === \App\Http\Controllers\Api\MediaController::class
            && $user->hasPermission('media.manage');
    }

    public static function module(): array
    {
        return ['path' => '/panel/admin/public-relations-directory', 'title' => 'صفحه مدیران روابط عمومی',
            'description' => '', 'permission' => 'content.manage', 'resource' => '', 'section' => 'people',
            'showInNavigation' => true, 'canCreate' => false, 'canEdit' => true, 'manager_directory_page' => true];
    }

    /** Explicit, repeatable setup; never run this on every request or reset CMS edits. */
    public function prepare(): array
    {
        return DB::transaction(function () {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            if ($prepared = Setting::where('key', self::PREPARED_KEY)->first()) {
                return [...$prepared->value, 'already_prepared' => true];
            }
            $author = User::where('organization_id', $root->id)->where('person_type', '!=', 'citizen')
                ->where('is_active', true)->orderBy('id')->firstOrFail();
            $category = Category::withTrashed()->firstOrCreate(
                ['type' => 'member', 'locale' => 'fa', 'slug' => 'official-member-menu-198'],
                ['organization_id' => $root->id, 'name' => self::GROUPS['5']['title'], 'is_active' => true]
            );
            if ($category->organization_id && (int) $category->organization_id !== $root->id) {
                throw new \RuntimeException('The public-relations category belongs to another portal.');
            }

            $changed = 0;
            $created = 0;
            $links = Link::withTrashed()->where('locale', 'fa')
                ->where(fn ($q) => $q->where('organization_id', $root->id)->orWhereNull('organization_id'))
                ->lockForUpdate()->get();
            foreach (self::GROUPS as $group => $definition) {
                $path = self::path((string) $group);
                $matches = $links->filter(fn ($link) => $link->url === $path || self::internalHref($link->url) === $path);
                if ($matches->isEmpty()) {
                    Link::create(['organization_id' => $root->id, 'locale' => 'fa', 'title' => $definition['title'],
                        'url' => $path, 'position' => 'useful', 'priority' => (int) $group, 'is_active' => true]);
                    $created++;
                } else {
                    foreach ($matches as $link) {
                        if (! $link->trashed() && $link->url !== $path) {
                            $link->update(['url' => $path]);
                            $changed++;
                        }
                    }
                }
            }
            $page = Content::withTrashed()->where('type', 'page')->where('locale', 'fa')->where('slug', self::PAGE_SLUG)->first();
            if ($page && ((int) $page->organization_id !== $root->id || ($page->metadata['manager_directory'] ?? null) !== 'public-relations')) {
                throw new \RuntimeException('The directory slug is already used by a different page.');
            }
            if (! $page) {
                $page = Content::create(['organization_id' => $root->id, 'author_id' => $author->id, 'type' => 'page', 'locale' => 'fa',
                    'slug' => self::PAGE_SLUG, 'title' => self::GROUPS['5']['title'],
                    'body' => '<p><a href="'.e(self::PDF_URL).'">'.e(self::PDF_TITLE).'</a></p>',
                    'status' => 'published', 'published_at' => now(), 'comments_enabled' => false,
                    'metadata' => ['manager_directory' => 'public-relations']]);
            }
            $result = ['updated_links' => $changed, 'created_links' => $created, 'page_id' => $page->id];
            Setting::create(['key' => self::PREPARED_KEY, 'value' => $result, 'is_public' => false]);
            return [...$result, 'already_prepared' => false];
        });
    }
}

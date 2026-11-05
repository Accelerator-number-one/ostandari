<?php

namespace App\Services;

use App\Models\{Content, CustomPage, Link, Menu, MenuItem, Organization, Service, Setting, Slide, User};
use Illuminate\Support\Facades\{DB, Schema};

final class LegacyLinkPages
{
    private const ASSET_KEYS = ['image', 'images', 'logo', 'banner', 'media', 'icon', 'thumbnail', 'cover', 'src', 'image_url', 'slogan'];
    private const REQUESTED_LINKS = [
        ['title' => 'خدمات الکترونیکی استان مازندران', 'url' => 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=1&OrgId=1'],
        ['title' => 'خدمات الکترونیکی', 'url' => 'https://ostan-mz.ir/Home/ElectronicServiceList?OrgId=1'],
        ['title' => 'پیوندها', 'url' => 'https://ostan-mz.ir/Home/LinkServiceList?OrgId=1'],
        ['title' => 'شرح وظایف و ماموریت‌های اساسی استانداری', 'url' => 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=49&OrgId=1'],
        ['title' => 'معاونت سیاسی امنیتی و اجتماعی', 'url' => 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=50&OrgId=1'],
    ];

    /** Match the old website itself, never a suffix match or another subdomain. */
    public static function source(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('~[\\\\\x00-\x20\x7f]~', $url)) return null;
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['host'] ?? ''), ['ostan-mz.ir', 'www.ostan-mz.ir'], true)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['', 'http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['port']) && !in_array($parts['port'], [80, 443], true)) return null;
        $path = rtrim(strtolower($parts['path'] ?? '/'), '/') ?: '/';
        $query = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            if ($pair === '') continue;
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[] = rawurlencode(strtolower(urldecode($key))).'='.rawurlencode(urldecode($value));
        }
        sort($query, SORT_STRING);
        return 'https://ostan-mz.ir'.$path.($query ? '?'.implode('&', $query) : '');
    }

    public static function key(string $url): ?string
    {
        return ($source = self::source($url)) ? hash('sha256', $source) : null;
    }

    public static function destination(string $url): ?string
    {
        if (!($source = self::source($url))) return null;
        // Preserve the real local directories that have already been implemented.
        $knownUrl = preg_replace_callback('~([?&])(menuid|orgid)=~', fn ($match) => $match[1].($match[2] === 'menuid' ? 'MenuId' : 'OrgId').'=', $source);
        if ($path = ManagerPages::internalHref($knownUrl) ?? LocalLinks::destination($knownUrl)) return $path;
        if (in_array(parse_url($source, PHP_URL_PATH), ['/', '/home', '/home/index', '/sariostandari'], true)
            && !parse_url($source, PHP_URL_QUERY)) return '/';
        $path = '/imported-pages/'.substr(hash('sha256', $source), 0, 24);
        $fragment = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_FRAGMENT);
        return $path.($fragment ? '#'.rawurlencode(rawurldecode($fragment)) : '');
    }

    /** Operate only on href attributes; images and downloads on other hosts stay intact. */
    public static function html(string $html): string
    {
        return preg_replace_callback('~(<a\b[^>]*\bhref\s*=\s*)(["\'])(.*?)\2~isu', function ($match) {
            $path = self::destination($match[3]);
            return $path ? $match[1].$match[2].e($path).$match[2] : $match[0];
        }, $html);
    }

    private static function htmlLinks(string $html, string $title): array
    {
        if (stripos($html, 'ostan-mz.ir') === false) return [];
        $html = Html::clean($html);
        preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~isu', $html, $matches, PREG_SET_ORDER);
        return array_map(fn ($match) => ['url' => $match[2], 'title' => trim(strip_tags(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: $title], $matches);
    }

    private static function references(array $data): array
    {
        $items = [];
        $url = $data['url'] ?? $data['href'] ?? null;
        if (is_string($url)) $items[] = ['url' => $url, 'title' => $data['title'] ?? $data['name'] ?? 'صفحه'];
        foreach ($data as $key => $value) if (is_array($value) && !in_array($key, self::ASSET_KEYS, true)) $items = [...$items, ...self::references($value)];
        return $items;
    }

    private static function localize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::ASSET_KEYS, true)) continue;
            if (is_array($value)) $data[$key] = self::localize($value);
            elseif (in_array($key, ['url', 'href'], true) && is_string($value)) $data[$key] = self::destination($value) ?? $value;
        }
        return $data;
    }

    /** No fetching, seeding or account changes. Existing page bodies are never overwritten. */
    public function prepare(): array
    {
        if (!CustomPages::ready() || !Schema::hasColumn('custom_pages', 'legacy_source_key')) {
            throw new \RuntimeException('ابتدا php artisan migrate را اجرا کنید.');
        }
        return DB::transaction(function () {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            $author = User::where('organization_id', $root->id)->where('person_type', 'staff')->where('is_active', true)->orderBy('id')->firstOrFail();
            $scope = fn ($query) => $query->where('organization_id', $root->id)->orWhereNull('organization_id');
            $records = collect();
            foreach ([Link::class, Slide::class, Service::class] as $model) {
                $records = $records->concat($model::where($scope)->lockForUpdate()->get());
            }
            $menus = Menu::where($scope)->pluck('id');
            $records = $records->concat(MenuItem::whereIn('menu_id', $menus)->lockForUpdate()->get());
            $settings = Setting::where('is_public', true)->whereIn('key', ['site.footer', 'site.socials', 'site.app_url'])->lockForUpdate()->get();
            $references = $records->map(fn ($record) => ['url' => $record->url, 'title' => $record->title ?: ($record->name ?: 'صفحه')])->all();
            $ui = app(PortalView::class);
            $references = [...$references, ...self::REQUESTED_LINKS, ...self::references($ui->data('official-site')['links']),
                ...self::references($ui->data('frontend')['officialNavigation'])];
            foreach ($settings as $setting) {
                if (is_array($setting->value)) $references = [...$references, ...self::references($setting->value)];
                elseif ($setting->key === 'site.app_url' && is_string($setting->value)) $references[] = ['url' => $setting->value, 'title' => 'نصب اپلیکیشن استان یار'];
            }
            foreach (Content::where($scope)->whereNotNull('body')->cursor() as $content) {
                $references = [...$references, ...self::htmlLinks($content->body, $content->title)];
            }
            foreach (CustomPage::where('organization_id', $root->id)->cursor() as $page) {
                $references = [...$references, ...self::htmlLinks($page->body, $page->title)];
            }
            $result = ['created' => 0, 'existing' => 0, 'updated_links' => 0, 'pages' => []];
            $sources = [];
            foreach ($references as $reference) {
                if (!is_string($reference['url'] ?? null) || RetiredSections::entry($reference)) continue;
                $source = self::source($reference['url']);
                $path = self::destination($reference['url']);
                if (!$source || !str_starts_with($path ?? '', '/imported-pages/')) continue;
                $key = hash('sha256', $source);
                $sources[$key] ??= ['url' => $source, 'path' => parse_url($path, PHP_URL_PATH),
                    'title' => mb_substr(trim(strip_tags((string)$reference['title'])) ?: 'صفحه', 0, 255)];
            }
            foreach ($sources as $key => $source) {
                $page = CustomPage::withTrashed()->where('organization_id', $root->id)->where('legacy_source_key', $key)->lockForUpdate()->first();
                if ($page) $result['existing']++;
                else {
                    if (CustomPage::withTrashed()->where('organization_id', $root->id)->where('path', $source['path'])->exists()) {
                        throw new \RuntimeException('مسیر صفحه از قبل استفاده شده است: '.$source['path']);
                    }
                    $page = CustomPage::create(['organization_id' => $root->id, 'author_id' => $author->id,
                        'title' => $source['title'], 'path' => $source['path'], 'body' => '',
                        'legacy_source_url' => $source['url'], 'legacy_source_key' => $key,
                        'link_style' => 'text', 'placements' => [], 'status' => 'published', 'priority' => 0]);
                    $result['created']++;
                }
                $result['pages'][] = ['id' => $page->id, 'title' => $page->title, 'path' => $page->path];
            }
            foreach ($records as $record) {
                if (!is_string($record->url) || RetiredSections::entry(['url' => $record->url])) continue;
                if ($path = self::destination($record->url)) {
                    $record->update(['url' => $path]);
                    $result['updated_links']++;
                }
            }
            foreach ($settings as $setting) {
                $value = is_array($setting->value) ? self::localize($setting->value) : ($setting->key === 'site.app_url' && is_string($setting->value) ? (self::destination($setting->value) ?? $setting->value) : $setting->value);
                if ($value !== $setting->value) $setting->update(['value' => $value]);
            }
            return $result;
        });
    }
}

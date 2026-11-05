<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Content;
use App\Models\County;
use App\Models\Link;
use App\Models\Member;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficialPublicSiteSeeder extends Seeder
{
    private const IMPORT_KEY = 'imports.official-public-site.1405-07-26.v1';
    private array $source;
    private array $pages;
    private int $rootId;
    private int $authorId;

    public function run(): void
    {
        $path = database_path('data/official-site.json');
        if (! is_file($path)) {
            throw new \RuntimeException('Missing database/data/official-site.json.');
        }
        $this->source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (Setting::where('key', self::IMPORT_KEY)->exists()) {
            $this->command?->info('This snapshot was already imported. Existing edits were preserved.');
            return;
        }
        $this->pages = array_column($this->source['pages'], null, 'id');
        $root = Organization::active()->where('slug', 'mazandaran')->firstOrFail();
        $author = User::where('username', 'admin')->where('person_type', 'staff')->where('is_active', true)->firstOrFail();
        $this->rootId = $root->id;
        $this->authorId = $author->id;

        DB::transaction(function () {
            $this->seedOrganizations();
            $this->seedArticles();
            $this->seedPages();
            $this->seedNavigation('official-main', 'منوی اصلی سایت', $this->source['main']);
            $this->seedNavigation('official-utility', 'دسترسی‌های بالای سایت', $this->source['utility']);
            $this->seedNavigation('official-footer', 'دسترسی آسان', $this->source['footer']);
            $this->seedServicesAndLinks();
            $this->seedHome();
            Setting::create(['key' => self::IMPORT_KEY, 'value' => ['source_sha256' => $this->source['source_sha256'], 'imported_at' => now()->toIso8601String()], 'is_public' => false]);
        });
        $this->command?->info('Official public-site data imported. Existing records and accounts were preserved.');
    }

    private function seedOrganizations(): void
    {
        foreach ($this->source['counties'] as $index => $item) {
            $slugs = [$item['slug'], ...$item['aliases']];
            $county = County::withTrashed()->whereIn('slug', $slugs)->orderBy('id')->first();
            $county ??= County::create(['name' => $item['name'], 'slug' => $item['slug'], 'map_key' => $item['slug'], 'priority' => $index + 1, 'is_active' => true]);
            if ($county->trashed() || ! $county->is_active) {
                continue;
            }
            $existing = Organization::withTrashed()->where(fn ($q) => $q->where('county_id', $county->id)->where('type', 'county'))->exists();
            if (! $existing && ! Organization::withTrashed()->where('slug', 'county-'.$item['slug'])->exists()) {
                Organization::create(['parent_id' => $this->rootId, 'county_id' => $county->id, 'type' => 'county', 'name' => $item['source_title'], 'slug' => 'county-'.$item['slug'], 'priority' => $index + 1]);
            }
        }
        $units = [];
        foreach ($this->source['organizations'] as $index => $item) {
            if ($item['type'] !== 'department') {
                continue;
            }
            $parent = $item['parent'] ? ($units[$item['parent']] ?? null) : $this->rootId;
            if (! $parent) {
                continue;
            }
            $unit = Organization::withTrashed()->where(fn ($q) => $q->where('slug', $item['slug'])->orWhere(fn ($q) => $q->where('parent_id', $parent)->where('type', 'department')->where('name', $item['name'])))->orderBy('id')->first();
            $unit ??= Organization::create(['parent_id' => $parent, 'type' => 'department', 'name' => $item['name'], 'slug' => $item['slug'], 'priority' => 100 + $index, 'is_active' => true]);
            if (! $unit->trashed() && $unit->is_active) {
                $units[$item['menu_id']] = $unit->id;
            }
        }
    }

    private function seedArticles(): void
    {
        foreach ($this->source['news'] as $index => $item) {
            $category = Category::withTrashed()->firstOrCreate(['type' => $item['type'], 'locale' => 'fa', 'slug' => $item['type'] === 'document' ? 'official-regulations' : 'official-'.$item['type']], ['name' => ['news' => 'اخبار استانداری', 'tender' => 'مناقصات و مزایدات', 'document' => 'قوانین و مقررات'][$item['type']], 'organization_id' => $this->rootId, 'is_active' => true]);
            if ($category->trashed() || ! $category->is_active) {
                continue;
            }
            $body = '<p>'.$this->escape($item['excerpt']).'</p><p>این مطلب چکیدهٔ منتشرشده در صفحهٔ اصلی است؛ برای متن کامل، صفحهٔ منبع را باز کنید.</p><p><a href="'.$this->escape($item['source_url']).'">صفحهٔ منبع</a></p>';
            Content::withTrashed()->firstOrCreate(['type' => $item['type'], 'locale' => 'fa', 'slug' => $item['slug']], [
                'organization_id' => $this->rootId, 'category_id' => $category->id, 'author_id' => $this->authorId,
                'title' => $item['title'], 'excerpt' => $item['excerpt'], 'body' => $body, 'status' => 'published',
                'published_at' => $item['published_at'], 'priority' => $index + 1, 'comments_enabled' => false,
                'metadata' => ['source_url' => $item['source_url'], 'source_id' => $item['source_id'], 'source_date' => $item['source_date'], 'source_sha256' => $this->source['source_sha256'], 'content_completeness' => 'homepage_excerpt', 'design_image' => $item['image'], 'snapshot_date' => $this->source['snapshot_date']],
            ]);
        }
    }

    private function seedPages(): void
    {
        foreach ($this->pages as $item) {
            if (in_array($item['kind'], ['home', 'county', 'article'], true) || in_array($item['id'], ['register', 'english', 'rss'], true)) {
                continue;
            }
            $body = '';
            if ($item['children']) {
                $body .= '<ul>';
                foreach ($item['children'] as $key) {
                    $child = $this->pages[$key] ?? null;
                    if ($child) {
                        $body .= '<li><a href="'.$this->escape($child['href']).'">'.$this->escape($child['title']).'</a></li>';
                    }
                }
                $body .= '</ul>';
            }
            if ($item['source_url']) {
                $body .= '<p><a href="'.$this->escape($item['source_url']).'">مشاهدهٔ اطلاعات رسمی '.$this->escape($item['title']).' در صفحهٔ منبع</a></p>';
            }
            $body = $body ?: '<p>برای دسترسی به بخش‌های مرتبط، فهرست سایت را مشاهده کنید.</p>';
            Content::withTrashed()->firstOrCreate(['type' => 'page', 'locale' => 'fa', 'slug' => 'official-page-'.$item['id']], [
                'organization_id' => $this->rootId, 'author_id' => $this->authorId, 'title' => $item['title'],
                'body' => $body, 'status' => 'published', 'published_at' => '2026-10-18 00:00:00',
                'metadata' => ['source_url' => $item['source_url'], 'source_sha256' => $this->source['source_sha256'], 'content_completeness' => 'source_reference', 'snapshot_date' => $this->source['snapshot_date']],
            ]);
        }
    }

    private function seedNavigation(string $key, string $title, array $ids): void
    {
        $menu = Menu::withTrashed()->firstOrCreate(['key' => $key, 'locale' => 'fa'], ['name' => $title, 'is_active' => true]);
        if ($menu->trashed() || ! $menu->is_active) {
            return;
        }
        $walk = function (array $children, ?int $parent = null, int $depth = 0) use (&$walk, $menu): void {
            if ($depth > 6) {
                throw new \RuntimeException('Unexpected menu depth.');
            }
            foreach ($children as $priority => $key) {
                $source = $this->pages[$key] ?? throw new \RuntimeException('Missing menu source '.$key);
                $item = MenuItem::withTrashed()->firstOrCreate(['menu_id' => $menu->id, 'parent_id' => $parent, 'title' => $source['title']], ['url' => $source['href'], 'target' => '_self', 'priority' => $priority + 1, 'is_active' => true]);
                if (! $item->trashed() && $item->is_active && $source['children']) {
                    $walk($source['children'], $item->id, $depth + 1);
                }
            }
        };
        $walk($ids);
    }

    private function seedServicesAndLinks(): void
    {
        foreach ($this->pages['services']['children'] as $key) {
            $item = $this->pages[$key];
            if ($item['kind'] === 'services') {
                Service::withTrashed()->firstOrCreate(['locale' => 'fa', 'slug' => 'official-'.$key], ['organization_id' => $this->rootId, 'name' => $item['title'], 'url' => $item['source_url'], 'is_active' => true]);
            }
        }
        $links = $this->source['links'];
        foreach ($this->source['useful'] as $key) {
            $item = $this->pages[$key];
            if ($item['source_url']) {
                $links[] = ['title' => $item['title'], 'url' => $item['source_url'], 'position' => 'useful'];
            }
        }
        foreach ($links as $index => $item) {
            Link::withTrashed()->firstOrCreate(['locale' => 'fa', 'url' => $item['url']], ['title' => $item['title'], 'position' => $item['position'], 'priority' => $index + 1, 'is_active' => true]);
        }
    }

    private function seedHome(): void
    {
        $poll = Poll::withTrashed()->firstOrCreate(['locale' => 'fa', 'question' => $this->source['poll']['question']], ['status' => 'active', 'description' => 'نظرسنجی منتشرشده در صفحهٔ اصلی استانداری مازندران؛ نتایج این پرتال مستقل است.']);
        if (! $poll->trashed()) {
            foreach ($this->source['poll']['options'] as $priority => $title) {
                PollOption::firstOrCreate(['poll_id' => $poll->id, 'title' => $title], ['priority' => $priority + 1]);
            }
        }
        $governor = $this->source['governor'];
        Member::withTrashed()->firstOrCreate(['locale' => 'fa', 'kind' => 'governor', 'name' => $governor['name']], ['organization_id' => $this->rootId, 'position' => $governor['position'], 'email' => $governor['email'], 'phone' => $governor['phone'], 'is_current' => ! Member::active()->where('kind', 'governor')->where('is_current', true)->exists(), 'is_active' => true]);
        foreach ($this->source['slides'] as $index => $slide) {
            Slide::withTrashed()->firstOrCreate(['locale' => 'fa', 'kind' => 'slide', 'title' => $slide['title']], ['url' => $slide['href'], 'priority' => $index + 1, 'is_active' => true]);
        }
        foreach (['site.name' => 'استانداری مازندران', 'site.slogan' => $this->source['slides'][0]['title'], 'site.contact' => $this->source['contact']] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'is_public' => true]);
        }
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

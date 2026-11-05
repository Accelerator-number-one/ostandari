<?php

namespace App\Services;

use App\Models\{Content, Organization, Setting, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ServiceAgreementPages
{
    public const IMPORT_KEY = 'imports.service-agreements.2026-10-25.v1';
    public const PAGES = [
        'letter-tracking' => [
            'label' => 'رهگیری نامه‌ها', 'path' => '/section/menu-177',
            'slug' => 'official-page-menu-177', 'title' => 'توافقنامه سطح خدمت «رهگیری نامه‌ها»',
        ],
        'government-network-support' => [
            'label' => 'پشتیبانی شبکه دولت', 'path' => '/section/menu-176',
            'slug' => 'official-page-menu-176', 'title' => 'بیانیه سطح توافق خدمات «درخواست پشتیبانی شبکه دولت»',
        ],
    ];

    public static function keyForPath(string $path): ?string
    {
        foreach (self::PAGES as $key => $page) if ($page['path'] === $path) return $key;
        return null;
    }

    public static function modules(): array
    {
        $modules = [];
        foreach (self::PAGES as $key => $page) {
            $modules[] = ['path' => '/panel/admin/'.$key, 'title' => $page['label'], 'description' => '',
                'permission' => 'content.manage', 'resource' => '', 'section' => 'services',
                'showInNavigation' => true, 'canCreate' => false, 'canEdit' => true, 'service_agreement' => $key];
        }
        return $modules;
    }

    public static function query(string $key, int $organizationId): Builder
    {
        if (!isset(self::PAGES[$key])) throw new \InvalidArgumentException('Unknown service-agreement page.');
        $imported=Setting::where('key', self::IMPORT_KEY)->exists();
        return Content::where('type', 'page')->where('locale', 'fa')->where('organization_id', $organizationId)
            ->where(function($query)use($key,$imported){
                $query->where('metadata->service_agreement', $key);
                if(!$imported)$query->orWhere(fn($q)=>$q->where('slug',self::PAGES[$key]['slug'])->whereNull('metadata->service_agreement'));
            });
    }

    /** Replace only the two requested pages once; later CMS edits survive every deployment. */
    public function import(): array
    {
        if ($record = Setting::where('key', self::IMPORT_KEY)->first()) return [...$record->value, 'already_imported' => true];
        return DB::transaction(function () {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            if ($record = Setting::where('key', self::IMPORT_KEY)->first()) return [...$record->value, 'already_imported' => true];
            $author = User::where('organization_id', $root->id)->where('person_type', 'staff')->where('is_active', true)->orderBy('id')->firstOrFail();
            $result = ['page_ids' => [], 'previous' => [], 'imported_at' => now()->toIso8601String()];
            foreach (self::PAGES as $key => $definition) {
                $matches = Content::withTrashed()->where('type', 'page')->where('locale', 'fa')
                    ->where(fn ($q) => $q->where('slug', $definition['slug'])->orWhere('metadata->service_agreement', $key))->lockForUpdate()->get();
                if ($matches->count() > 1) throw new \RuntimeException('Ambiguous service-agreement page: '.$key);
                $page = $matches->first();
                if ($page && ($page->trashed() || $page->organization_id !== null && (int) $page->organization_id !== $root->id)) {
                    throw new \RuntimeException('Service-agreement page is deleted or belongs to another portal: '.$key);
                }
                $body = Html::clean(file_get_contents(database_path('data/service-agreements/'.$key.'.html')));
                if (trim(strip_tags($body)) === '') throw new \RuntimeException('Missing service-agreement text: '.$key);
                if ($page) $result['previous'][$key] = $page->only(['title', 'body', 'status', 'published_at', 'expires_at', 'organization_id', 'metadata']);
                else $page = new Content(['type' => 'page', 'locale' => 'fa', 'slug' => $definition['slug'], 'author_id' => $author->id, 'comments_enabled' => false]);
                $page->fill(['organization_id' => $root->id, 'title' => $definition['title'], 'body' => $body,
                    'status' => 'published', 'published_at' => now()->subMinute(), 'expires_at' => null,
                    'metadata' => array_replace($page->metadata ?? [], ['service_agreement' => $key, 'content_completeness' => 'full_text', 'content_source' => 'user_supplied'])]);
                $page->save();
                $result['page_ids'][$key] = $page->id;
            }
            Setting::create(['key' => self::IMPORT_KEY, 'value' => $result, 'is_public' => false]);
            return [...$result, 'already_imported' => false];
        });
    }
}

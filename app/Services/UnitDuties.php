<?php

namespace App\Services;

use App\Models\{Content, Organization, Setting, User};
use Illuminate\Support\Facades\DB;

final class UnitDuties
{
    public const IMPORT_KEY = 'imports.unit-duties.2026-10-26.v1';
    private static ?array $catalogCache = null;

    public static function catalog(): array
    {
        return self::$catalogCache ??= json_decode(file_get_contents(database_path('data/unit-duties-2026-10-26.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function groups(): array
    {
        return array_column(self::catalog()['groups'], 'title');
    }

    public static function sourceUnit(mixed $id): ?array
    {
        if (!is_scalar($id) || !ctype_digit((string) $id)) return null;
        foreach (self::catalog()['units'] as $unit) if ($unit['source_id'] === (int) $id) return $unit;
        return null;
    }

    public static function sourceForPath(string $path): ?array
    {
        foreach (self::catalog()['units'] as $unit) if ($unit['legacy_path'] === $path) return $unit;
        return null;
    }

    public static function groupForPath(string $path): ?string
    {
        foreach (self::catalog()['groups'] as $group) if ($group['path'] === $path) return $group['title'];
        return null;
    }

    public static function report(): ?array
    {
        return Setting::where('key', self::IMPORT_KEY)->value('value');
    }

    public static function importedPage(string $path, ?int $organizationId): ?Content
    {
        $source = self::sourceForPath($path);
        if (!$source || !$organizationId) return null;
        return Content::visible()->where('organization_id', $organizationId)->where('locale', 'fa')->where('type', 'duty')
            ->where('metadata->duty_source_id', $source['source_id'])->first();
    }

    public function import(): array
    {
        $source = self::catalog();
        if (count($source['units']) !== 24 || count($source['groups']) !== 6) throw new \RuntimeException('Unexpected source duty directory.');
        $hash = hash_file('sha256', database_path('data/unit-duties-2026-10-26.json'));
        if (($old = self::report()) && ($old['source_sha256'] ?? '') === $hash) return [...$old, 'already_imported' => true];

        return DB::transaction(function () use ($source, $hash) {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            $old = self::report();
            if ($old && ($old['source_sha256'] ?? '') === $hash) return [...$old, 'already_imported' => true];
            if ($old && (int) $old['organization_id'] !== $root->id) throw new \RuntimeException('The duty import belongs to a different portal.');
            $author = User::where('organization_id', $root->id)->where('person_type', 'staff')->where('is_active', true)->orderBy('id')->firstOrFail();
            $result = array_replace(['organization_id' => $root->id, 'content_ids' => [], 'previous' => [], 'preserved_ids' => [], 'skipped_ids' => []], $old ?? []);
            $pending = [];
            foreach ($source['units'] as $unit) {
                if ($unit['status'] !== 'complete') { $pending[] = $unit['source_id']; continue; }
                if (isset($result['content_ids'][$unit['source_id']]) || in_array($unit['source_id'], $result['skipped_ids'], true)) continue;
                if (mb_strlen($unit['source_text'] ?? '') < 250 || !hash_equals($unit['source_sha256'], hash('sha256', $unit['source_text']))) throw new \RuntimeException('Incomplete duty source: '.$unit['source_id']);
                $body = Html::clean($unit['body']);
                if (mb_strlen(strip_tags($body)) < 250) throw new \RuntimeException('Empty duty body: '.$unit['source_id']);
                $matches = Content::withTrashed()->where('locale', 'fa')->whereIn('type', ['page', 'duty'])
                    ->where(fn ($q) => $q->where('organization_id', $root->id)->orWhereNull('organization_id'))
                    ->where(fn ($q) => $q->where('metadata->duty_source_id', $unit['source_id'])->orWhere('slug', 'official-page-menu-'.$unit['source_id'])->orWhere('slug', 'official-duty-menu-'.$unit['source_id']))->lockForUpdate()->get();
                if ($matches->count() > 1) throw new \RuntimeException('Ambiguous existing duty: '.$unit['source_id']);
                $record = $matches->first();
                if ($record?->trashed()) { $result['skipped_ids'][] = $unit['source_id']; continue; }
                $replace = !$record || ($record->metadata['content_completeness'] ?? '') === 'source_reference'
                    && trim(html_entity_decode(strip_tags($record->body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === 'مشاهدهٔ اطلاعات رسمی '.$record->title.' در صفحهٔ منبع';
                if ($record) $result['previous'][$unit['source_id']] = $record->only(['type', 'title', 'slug', 'body', 'status', 'published_at', 'organization_id', 'metadata']);
                else $record = new Content(['author_id' => $author->id, 'locale' => 'fa', 'comments_enabled' => false, 'slug' => 'official-duty-menu-'.$unit['source_id']]);
                $slug = $record->slug;
                if (Content::withTrashed()->where('type', 'duty')->where('locale', 'fa')->where('slug', $slug)->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) throw new \RuntimeException('Duty slug belongs to another record: '.$slug);
                $record->fill(['type' => 'duty', 'organization_id' => $root->id]);
                if ($replace) {
                    $record->fill(['title' => $unit['title'], 'body' => $body, 'priority' => $record->priority ?: $unit['priority']]);
                    if (!$record->exists) $record->fill(['status' => 'published', 'published_at' => $unit['source_published_at']]);
                }
                else $result['preserved_ids'][] = $unit['source_id'];
                $record->metadata = array_replace($record->metadata ?? [], [
                    'duty_group' => $record->metadata['duty_group'] ?? $unit['group'], 'duty_source_id' => $unit['source_id'],
                    'source_url' => $unit['source_url'], 'source_sha256' => $unit['source_sha256'], 'source_retrieval' => $source['retrieval'],
                    'content_completeness' => $replace ? 'full_text' : 'editor_preserved', 'source_checked_on' => $source['retrieved_on'],
                ]);
                $record->save();
                $result['content_ids'][$unit['source_id']] = $record->id;
            }
            $result = [...$result, 'source_sha256' => $hash, 'pending_source_ids' => $pending, 'imported_at' => now()->toIso8601String()];
            Setting::updateOrCreate(['key' => self::IMPORT_KEY], ['value' => $result, 'is_public' => false]);
            return [...$result, 'already_imported' => false];
        });
    }
}

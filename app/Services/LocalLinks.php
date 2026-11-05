<?php

namespace App\Services;

use App\Models\{Link, Organization};
use Illuminate\Support\Facades\DB;

final class LocalLinks
{
    /** This destination is an index of our existing records, not copied remote content. */
    public static function destination(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) return null;
        if (!in_array(strtolower($parts['host'] ?? ''), ['ostan-mz.ir', 'www.ostan-mz.ir'], true)) return null;
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['OrgId']) && (!is_scalar($query['OrgId']) || (string)$query['OrgId'] !== '1')) return null;
        $path = strtolower(rtrim($parts['path'] ?? '', '/'));
        if ($path === '/home/linkservicelist') return '/links';
        if ($path === '/home/showdetailsmenucontent' && ($query['MenuId'] ?? null) === '9') return '/links';
        return null;
    }

    public static function directory(?int $organizationId, string $locale, bool $includeShared): array
    {
        if (!$organizationId) return [];
        return RetiredSections::entries(Link::active()->where('locale', $locale)
            ->where(function ($query) use ($organizationId, $includeShared) {
                $query->where('organization_id', $organizationId);
                if ($includeShared) $query->orWhereNull('organization_id');
            })
            ->with(['image', 'category'])->orderBy('priority')->orderBy('title')->orderBy('id')->get()->toArray());
    }

    public static function groups(array $links): array
    {
        $groups = [];
        foreach (RetiredSections::entries($links) as $link) {
            $custom = !empty($link['custom_page']);
            $key = $custom ? 'custom-pages' : 'category-'.($link['category_id'] ?? 'other');
            $title = $custom ? 'صفحه‌های سایت' : ($link['category']['name'] ?? 'سایر پیوندها');
            $groups[$key] ??= ['key' => $key, 'title' => $title, 'items' => []];
            $groups[$key]['items'][] = $link;
        }
        return array_values($groups);
    }

    /** Change only the two old link-index URLs; preserve labels, other hosts and CMS edits. */
    public function prepare(): int
    {
        return DB::transaction(function () {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            $links = Link::where(fn ($query) => $query->where('organization_id', $root->id)->orWhereNull('organization_id'))
                ->lockForUpdate()->get();
            $updated = 0;
            foreach ($links as $link) {
                if ($path = self::destination($link->url)) {
                    $link->update(['url' => $path]);
                    $updated++;
                }
            }
            return $updated;
        });
    }
}

<?php

namespace App\Services;

use App\Models\{Content, Organization};
use Illuminate\Database\Eloquent\Builder;

final class NewsFeeds
{
    public const LABELS = ['province' => 'اخبار استان', 'governorates' => 'اخبار فرمانداری‌ها',
        'counties' => 'اخبار شهرستان‌ها', 'crisis' => 'اخبار مدیریت بحران'];

    public static function apply(Builder $query, string $scope): Builder
    {
        $root = Organization::active()->where('type', 'governorate')->value('id');
        // The publishing organization determines this feed, including imported news
        // whose old metadata names another group.
        if ($scope === 'governorates') {
            return $query->where('type', 'news')->where(fn (Builder $feed) =>
                $feed->whereHas('organization', fn (Builder $org) => $org->active()->where('type', 'county')
                    ->whereHas('county', fn (Builder $county) => $county->active()))
                ->orWhere(fn (Builder $curated) => $curated->where('metadata->news_scope', 'governorates')
                    ->where(fn (Builder $org) => $org->where('organization_id', $root)->orWhereNull('organization_id'))));
        }
        if ($scope === 'province') {
            $query->where(fn (Builder $org) => $org->where('organization_id', $root)->orWhereNull('organization_id'));
        }
        return $query->where('type', 'news')->where(function (Builder $feed) use ($scope, $root) {
            $feed->where('metadata->news_scope', $scope);
            if ($scope === 'province') {
                $feed->orWhere(fn (Builder $old) => $old->whereNull('metadata->news_scope')
                    ->where(fn (Builder $org) => $org->where('organization_id', $root)->orWhereNull('organization_id')));
            }
        });
    }

    public static function home(string $locale, ?int $organizationId): array
    {
        $feeds = [];
        foreach (self::LABELS as $scope => $_) {
            $query = Content::visible()->where('locale', $locale)->with(['cover', 'category', 'organization.county'])
                ->where(fn (Builder $org) => $org->whereNull('organization_id')
                    ->orWhereHas('organization', fn (Builder $organization) => $organization->active()));
            if ($organizationId !== null) $query->where('organization_id', $organizationId);
            $feeds[$scope] = self::apply($query, $scope)->orderByDesc('is_featured')
                ->orderBy('priority')->latest('published_at')->limit(4)->get();
        }
        return $feeds;
    }
}

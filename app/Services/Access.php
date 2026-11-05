<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Organization;
use App\Models\User;

class Access
{
    public static function organizations(User $user): array
    {
        return $user->organization_id ? [(int) $user->organization_id] : [];
    }

    public static function scope($query, User $user, bool $tenant = true, string $column = 'organization_id')
    {
        if (! $user->hasGlobalAccess()) {
            match ($query->getModel()->getTable()) {
                'organizations' => $query->whereIn('id', self::organizations($user)),
                'counties' => $query->where('id', $user->organization?->county_id ?? 0),
                'menu_items' => $query->whereIn('menu_id', self::scope(\App\Models\Menu::query(), $user)->select('id')),
                'poll_options' => $query->whereIn('poll_id', self::scope(\App\Models\Poll::query(), $user)->select('id')),
                'gallery_items' => $query->whereIn('gallery_id', self::scope(Gallery::query(), $user)->select('id')),
                default => null,
            };
        }
        if ($tenant && ! $user->hasGlobalAccess() && ! in_array($query->getModel()->getTable(), ['organizations', 'counties', 'menu_items', 'poll_options', 'gallery_items'], true)) {
            $query->where(fn ($q) => $q->whereIn($column, self::organizations($user))->when($user->organization?->type === 'governorate', fn ($q) => $q->orWhereNull($column)));
        }

        return $query;
    }

    public static function organization(User $user, mixed $id): void
    {
        if (! $user->hasGlobalAccess()) {
            abort_unless(in_array((int) $id, self::organizations($user), true), 403, 'استان یا فرمانداری انتخاب‌شده خارج از محدودهٔ دسترسی شماست.');
        }
    }

    public static function gallery(User $user, int $id): void
    {
        self::organization($user, Gallery::findOrFail($id)->organization_id);
    }
}

<?php

namespace App\Services;

use App\Models\{StaffPortal, StaffPortalPage};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\Rule;

final class StaffPages
{
    public static function ready(): bool { return Schema::hasTable('staff_portal_pages'); }

    public static function navigation(StaffPortal $portal)
    {
        return self::ready() ? $portal->pages()->published()->where('show_in_navigation', true)->orderBy('priority')->orderBy('id')->get() : collect();
    }

    public static function save(StaffPortal $portal, array $input, ?StaffPortalPage $page = null): StaffPortalPage
    {
        StaffPortals::rejectOwnership($input);
        if ($page) abort_unless((int) $page->staff_portal_id === (int) $portal->id, 404);
        if (isset($input['path']) && is_string($input['path'])) $input['path'] = mb_strtolower(trim($input['path']));
        return DB::transaction(function () use ($portal, $input, $page) {
            // Serialize route allocation within one manager's page, including trashed routes.
            StaffPortal::whereKey($portal->id)->lockForUpdate()->firstOrFail();
            $data = Validator::make($input, [
                'title' => 'required|string|max:255',
                'path' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN][\pL\pN_-]*$/u',
                    Rule::unique('staff_portal_pages', 'path')->where('staff_portal_id', $portal->id)->ignore($page?->id)],
                'body' => 'required|string|max:1000000', 'nav_title' => 'nullable|string|max:120',
                'show_in_navigation' => 'required|boolean', 'status' => ['required', Rule::in(['draft', 'published'])],
                'published_at' => 'nullable|date', 'priority' => 'required|integer|min:0|max:100000',
            ], ['path.regex' => 'مسیر فقط می‌تواند شامل حروف، عدد، خط تیره و زیرخط باشد.',
                'path.unique' => 'این مسیر قبلاً در صفحه شما استفاده شده است؛ زباله‌دان را هم بررسی کنید.'])->validate();
            $data['body'] = StaffPortals::cleanBody($portal, $data['body']);
            if (trim(strip_tags($data['body'])) === '' && !str_contains($data['body'], '<img'))
                throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'محتوای صفحه را وارد کنید.']);
            $data['published_at'] ??= $data['status'] === 'published' ? ($page?->published_at ?? now()) : null;
            $page ??= new StaffPortalPage(['staff_portal_id' => $portal->id]);
            $page->fill($data)->save();
            Audit::record('staff-portal.page-saved', $page);
            return $page;
        });
    }
}

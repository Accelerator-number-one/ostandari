<?php

namespace App\Services;

use App\Models\{Member, Organization, Role, StaffPortal, StaffPortalAsset, StaffPortalItem, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

/** Personal pages own their records independently of the province/county content tables. */
final class StaffPortals
{
    public const ROLE = 'staff_manager';
    public const SYNC_KEY = 'imports.personal-portals.2026-11-03.v1';
    public const KINDS = ['slide' => 'اسلایدشو', 'news' => 'اخبار', 'announcement' => 'اطلاعیه‌ها', 'gallery' => 'گالری تصاویر', 'video' => 'گالری ویدئو', 'link' => 'پیوندها'];
    public const IT_MANAGER = 'سیده معصومه سجادیان امیری';
    public const IT_TITLE = 'مدیریت فناوری اطلاعات، امنیت فضای مجازی و شبکه دولت';

    public static function ready(): bool
    {
        return Schema::hasTable('staff_portals') && Schema::hasTable('staff_portal_items') && Schema::hasTable('staff_portal_assets');
    }

    public static function eligible($query)
    {
        return $query->active()->where('locale', 'fa')->where(function ($members) {
            $members->whereHas('category', fn ($category) => $category->where('slug', MemberDirectory::GROUPS['1']['slug'])->where('is_active', true))
                ->orWhere(fn ($governors) => $governors->where('kind', 'county_governor')->whereHas('category',
                    fn ($category) => $category->where('slug', MemberDirectory::GROUPS['4']['slug'])->where('is_active', true)));
        })
            ->whereHas('organization', fn ($q) => $q->where('type', 'governorate')->where('is_active', true));
    }

    public static function visible()
    {
        return StaffPortal::where('is_active', true)->whereHas('member', fn ($q) => self::eligible($q));
    }

    public static function resolve(string $slug): StaffPortal
    {
        abort_unless(self::ready(), 404);
        return self::visible()->with(['member.image', 'member.category', 'portrait', 'banner'])->where('slug', $slug)->firstOrFail();
    }

    public static function assertOwner(User $user, StaffPortal $portal): void
    {
        abort_unless($user->is_active && $user->isStaffManager() && $user->isStaff() && $portal->is_active &&
            (int) $portal->owner_user_id === (int) $user->id &&
            (int) $user->organization_id === (int) $portal->member?->organization_id &&
            self::eligible(Member::query())->whereKey($portal->member_id)->exists(), 403, 'این پنل فقط برای مدیر همین صفحه است.');
    }

    public static function synchronized(): bool
    {
        return \App\Models\Setting::where('key', self::SYNC_KEY)->exists();
    }

    public static function sync(bool $force = false): int
    {
        if (!self::ready()) return 0;
        if (!$force && self::synchronized()) return 0;
        return DB::transaction(function () use ($force) {
            $root = Organization::active()->where('type', 'governorate')->orderBy('id')->lockForUpdate()->first();
            if (!$root) return 0;
            if (!$force && self::synchronized()) return 0;
            $members = self::eligible(Member::query())->where('organization_id', $root->id)->orderBy('id')->get();
            // A first installation can still be importing the directory in another process.
            if ($members->isEmpty()) return 0;
            $existing = StaffPortal::whereIn('member_id', $members->modelKeys())->pluck('member_id')->all();
            $itTaken = StaffPortal::where('slug', 'it')->exists();
            $rows = [];
            foreach ($members as $member) {
                if (in_array($member->id, $existing, true)) continue;
                $it = !$itTaken && $member->kind === 'manager' && $member->name === self::IT_MANAGER;
                if ($it) $itTaken = true;
                $rows[] = ['member_id' => $member->id, ...self::initialValues($member, $it), 'created_at' => now(), 'updated_at' => now()];
            }
            // Avoid a database round trip for each person during a stateless cold start.
            if ($rows) StaffPortal::insertOrIgnore($rows);
            $saved = StaffPortal::whereIn('member_id', $members->modelKeys())->pluck('member_id')->all();
            foreach ($members as $member) if (!in_array($member->id, $saved, true)) self::createForMember($member);
            $role = Role::firstOrCreate(['name' => self::ROLE], ['label' => 'مدیر صفحه شخصی', 'permissions' => ['staff-portal.manage'], 'is_system' => true]);
            if ($role->label === 'مدیر صفحه ستادی') $role->update(['label' => 'مدیر صفحه شخصی']);
            \App\Models\Setting::updateOrCreate(['key' => self::SYNC_KEY], ['value' => ['member_ids' => $members->modelKeys(), 'completed_at' => now()->toIso8601String()], 'is_public' => false]);
            return count(array_diff(StaffPortal::whereIn('member_id', $members->modelKeys())->pluck('member_id')->all(), $existing));
        });
    }

    private static function initialValues(Member $member, bool $it): array
    {
        $governor = $member->kind === 'county_governor';
        return ['slug' => $it ? 'it' : ($governor ? 'governor-' : 'member-').$member->id,
            'title' => $it ? self::IT_TITLE : ($governor ? ($member->position ?: $member->name) : $member->name),
            'summary' => $member->bio ? strip_tags($member->bio) : null, 'body' => $member->body ? Html::clean($member->body) : null,
            'phone' => $member->phone, 'email' => $member->email, 'is_active' => true];
    }

    private static function createForMember(Member $member): StaffPortal
    {
        $it = $member->kind === 'manager' && $member->name === self::IT_MANAGER && !StaffPortal::where('slug', 'it')->where('member_id', '!=', $member->id)->exists();
        // A directory edit never replaces a manager's own content or existing URL.
        return StaffPortal::firstOrCreate(['member_id' => $member->id], self::initialValues($member, $it));
    }

    public static function syncMember(Member $member): void
    {
        if (!self::ready() || !self::eligible(Member::query())->whereKey($member->id)->exists()) return;
        self::createForMember($member);
    }

    public static function links(array $members): array
    {
        if (!$members) return [];
        $request = request();
        $links = $request->attributes->get('_staff_public_page_links');
        if ($links === null) {
            $links = self::ready() ? self::visible()->get()->mapWithKeys(fn ($portal) => [$portal->member_id => $portal->publicPath()])->all() : [];
            $request->attributes->set('_staff_public_page_links', $links);
        }
        return array_intersect_key($links, array_flip(array_column($members, 'id')));
    }

    public static function bootstrapDemo(): bool
    {
        $hash = config('website.staff_demo_hash');
        if (!$hash || !self::ready()) return false;
        $key = 'imports.staff-portal-test-account.2026-10-30.v1';
        if (\App\Models\Setting::where('key', $key)->exists()) return false;
        abort_unless(is_string($hash) && preg_match('~^\$2[ayb]\$\d{2}\$[./A-Za-z0-9]{53}$~', $hash), 422, 'A valid bcrypt hash is required.');
        return DB::transaction(function () use ($hash, $key) {
            $portal = self::visible()->where('slug', 'it')->lockForUpdate()->first();
            if (!$portal) return false;
            if (\App\Models\Setting::where('key', $key)->exists()) return false;
            $username = Validator::make(['username' => config('website.staff_demo_username')], ['username' => 'required|alpha_dash:ascii|max:80'])->validate()['username'];
            // Repeated deploys never reset a password, reuse a username, or replace an existing owner.
            if ($portal->owner_user_id || User::where('username', $username)->exists()) return false;
            $user = User::create(['username' => $username, 'password' => $hash, 'name' => $portal->member->name,
                'organization_id' => $portal->member->organization_id, 'role_id' => Role::where('name', self::ROLE)->value('id'),
                'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
            $portal->update(['owner_user_id' => $user->id]);
            \App\Models\Setting::create(['key' => $key, 'value' => ['user_id' => $user->id, 'portal_id' => $portal->id, 'created_at' => now()->toIso8601String()], 'is_public' => false]);
            return true;
        });
    }

    public static function rejectOwnership(array $input): void
    {
        abort_if(array_intersect(array_keys($input), ['id', 'staff_portal_id', 'member_id', 'owner_user_id', 'organization_id', 'user_id', 'role_id', 'access_scope', 'slug', 'is_active', 'deleted_at']), 403, 'مالک و مسیر صفحه از طریق این فرم قابل تغییر نیست.');
    }

    public static function asset(StaffPortal $portal, mixed $id): ?StaffPortalAsset
    {
        if (!$id) return null;
        return $portal->assets()->findOrFail($id);
    }

    public static function saveProfile(StaffPortal $portal, array $input): void
    {
        self::rejectOwnership($input);
        $data = Validator::make($input, ['title' => 'required|string|max:255', 'summary' => 'nullable|string|max:3000',
            'body' => 'nullable|string|max:1000000', 'phone' => 'nullable|string|max:60', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000', 'portrait_id' => 'nullable|integer', 'banner_id' => 'nullable|integer'])->validate();
        foreach (['portrait_id', 'banner_id'] as $key) if (array_key_exists($key, $data)) self::asset($portal, $data[$key]);
        $data['body'] = self::cleanBody($portal, $data['body'] ?? '');
        $portal->update($data);
        Audit::record('staff-portal.profile-updated', $portal);
    }

    public static function saveItem(StaffPortal $portal, array $input, ?StaffPortalItem $item = null): StaffPortalItem
    {
        self::rejectOwnership($input);
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(array_keys(self::KINDS))], 'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:3000', 'body' => 'nullable|string|max:1000000', 'asset_id' => 'nullable|integer',
            'url' => 'nullable|url:http,https|max:2000', 'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => 'nullable|date', 'priority' => 'required|integer|min:0|max:100000',
        ])->validate();
        if ($item) abort_unless((int) $item->staff_portal_id === (int) $portal->id, 404);
        self::asset($portal, $data['asset_id'] ?? null);
        $data['body'] = self::cleanBody($portal, $data['body'] ?? '');
        if (in_array($data['kind'], ['news', 'announcement'], true) && trim(strip_tags($data['body'])) === '')
            throw ValidationException::withMessages(['body' => 'متن کامل را وارد کنید.']);
        if (in_array($data['kind'], ['gallery', 'slide'], true) && empty($data['asset_id']))
            throw ValidationException::withMessages(['asset_id' => 'تصویر را بارگذاری یا از تصاویر خود انتخاب کنید.']);
        if (in_array($data['kind'], ['link', 'video'], true) && empty($data['url']))
            throw ValidationException::withMessages(['url' => 'نشانی پیوند یا ویدئو را وارد کنید.']);
        $data['published_at'] = $data['published_at'] ?? ($data['status'] === 'published' ? ($item?->published_at ?? now()) : null);
        $item ??= new StaffPortalItem(['staff_portal_id' => $portal->id]);
        $item->fill($data)->save();
        Audit::record('staff-portal.item-saved', $item);
        return $item;
    }

    public static function cleanBody(StaffPortal $portal, string $html): string
    {
        $html = Html::clean($html);
        // All embedded images use this manager's gallery; no remote pixels or other owner's drafts.
        if (preg_match_all('~<img\b[^>]*\bsrc="([^"]*)"~i', $html, $matches)) foreach ($matches[1] as $src) {
            $path = parse_url(html_entity_decode($src, ENT_QUOTES, 'UTF-8'), PHP_URL_PATH);
            $host = parse_url(html_entity_decode($src, ENT_QUOTES, 'UTF-8'), PHP_URL_HOST);
            if ($host && $host !== parse_url(config('app.url'), PHP_URL_HOST))
                throw ValidationException::withMessages(['body' => 'تصاویر متن را از گالری همین صفحه انتخاب کنید.']);
            if (!preg_match('~^/staff-assets/(\d+)/([a-zA-Z0-9-]+\.(?:webp|png|jpg|jpeg))$~', $path ?? '', $match) ||
                !$portal->assets()->whereKey($match[1])->where('name', $match[2])->exists())
                throw ValidationException::withMessages(['body' => 'تصویر متعلق به گالری همین صفحه نیست.']);
        }
        return $html;
    }

    public static function upload(StaffPortal $portal, UploadedFile $file): StaffPortalAsset
    {
        Validator::make(['file' => $file], ['file' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192'])->validate();
        $prepared = app(MediaUploadPreparer::class)->prepare($file);
        try {
            $bytes = file_get_contents($prepared->path);
            abort_unless($bytes !== false && strlen($bytes) <= 8 * 1024 * 1024, 422);
            return $portal->assets()->create(['name' => Str::uuid().'.'.$prepared->extension,
                'original_name' => $prepared->name, 'mime' => $prepared->mime, 'width' => $prepared->width,
                'height' => $prepared->height, 'size' => strlen($bytes), 'base64' => base64_encode($bytes)]);
        } finally { $prepared->cleanup(); }
    }
}

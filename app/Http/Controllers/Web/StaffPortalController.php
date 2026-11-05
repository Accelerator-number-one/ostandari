<?php

namespace App\Http\Controllers\Web;

use App\Models\{StaffPortal, StaffPortalAsset, StaffPortalItem, User};
use App\Services\{Audit, StaffPages, StaffPortals};
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class StaffPortalController
{
    private function portal(Request $request): StaffPortal { return $request->attributes->get('staff_portal'); }

    public function loginPage(Request $request, string $slug)
    {
        $portal = StaffPortals::resolve($slug);
        if ($request->user()?->isStaffManager() && (int) $portal->owner_user_id === (int) $request->user()->id) {
            StaffPortals::assertOwner($request->user(), $portal);
            return redirect($portal->adminPath());
        }
        return view('staff.login', ['portal' => $portal, 'title' => 'ورود به پنل '.$portal->member->name]);
    }

    public function login(Request $request, string $slug)
    {
        $portal = StaffPortals::resolve($slug);
        $data = $request->validate(['login' => 'required|string|max:255', 'password' => 'required|string|max:200']);
        $user = User::where('username', $data['login'])->first();
        if (!$user || !$user->is_active || !$user->password || !Hash::check($data['password'], $user->password))
            throw ValidationException::withMessages(['login' => 'نام کاربری یا رمز عبور صحیح نیست.']);
        StaffPortals::assertOwner($user, $portal);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        Audit::record('auth.staff-portal-login', $portal);
        return redirect($portal->adminPath());
    }

    public function logout(Request $request)
    {
        $path = $this->portal($request)->adminPath().'/login';
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect($path);
    }

    public function dashboard(Request $request)
    {
        $portal = $this->portal($request);
        $tab = $request->query('tab', 'profile');
        abort_unless(in_array($tab, ['profile', 'assets', 'account', 'pages', ...array_keys(StaffPortals::KINDS)], true), 404);
        if ($tab === 'pages') abort_unless(StaffPages::ready(), 503, 'آماده‌سازی صفحات در حال انجام است.');
        $query = $tab === 'pages' ? $portal->pages()->orderBy('priority')->latest('id') : $portal->items()->with('asset')->where('kind', $tab)->orderBy('priority')->latest('id');
        if ($request->boolean('trash')) $query->onlyTrashed();
        if ($request->filled('q')) {
            $request->validate(['q' => 'string|max:150']);
            $query->where('title', 'like', '%'.$request->input('q').'%');
        }
        return view('staff.dashboard', ['portal' => $portal, 'tab' => $tab, 'items' => $query->paginate(20)->withQueryString(),
            'assets' => $portal->assets()->latest('id')->paginate(30)->withQueryString(), 'title' => 'پنل '.$portal->member->name]);
    }

    public function profile(Request $request)
    {
        $portal = $this->portal($request);
        $request->validate(['portrait_file' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192', 'banner_file' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192']);
        DB::transaction(function () use ($request, $portal) {
            $data = $request->except(['_token', 'portrait_file', 'banner_file']);
            foreach (['portrait_file' => 'portrait_id', 'banner_file' => 'banner_id'] as $field => $key)
                if ($request->hasFile($field)) $data[$key] = StaffPortals::upload($portal, $request->file($field))->id;
            StaffPortals::saveProfile($portal, $data);
        });
        return redirect($portal->adminPath())->with('status', 'مشخصات صفحه ذخیره شد.');
    }

    public function editor(Request $request, string $slug, ?int $id = null)
    {
        $portal = $this->portal($request);
        $item = $id ? $portal->items()->findOrFail($id) : new StaffPortalItem(['kind' => $request->query('kind', 'news'), 'status' => 'draft', 'priority' => 0]);
        abort_unless(isset(StaffPortals::KINDS[$item->kind]), 404);
        return view('staff.editor', ['portal' => $portal, 'item' => $item, 'tab' => $item->kind,
            'assets' => $portal->assets()->latest('id')->get(), 'title' => $id ? 'ویرایش محتوا' : 'افزودن محتوا']);
    }

    public function save(Request $request, string $slug, ?int $id = null)
    {
        $portal = $this->portal($request);
        $item = $id ? $portal->items()->findOrFail($id) : null;
        $request->validate(['image_file' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192', 'images' => 'sometimes|array|max:20', 'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:8192']);
        $data = $request->except(['_token', 'image_file', 'images']);
        DB::transaction(function () use ($request, $portal, $item, &$data) {
            if ($request->hasFile('image_file')) $data['asset_id'] = StaffPortals::upload($portal, $request->file('image_file'))->id;
            $batch = $request->file('images', []);
            if ($batch && !$item && ($data['kind'] ?? null) === 'gallery') {
                foreach ($batch as $index => $file) StaffPortals::saveItem($portal, [...$data,
                    'title' => count($batch) > 1 ? ($data['title'].' — '.($index + 1)) : $data['title'],
                    'asset_id' => StaffPortals::upload($portal, $file)->id]);
            } else StaffPortals::saveItem($portal, $data, $item);
        });
        return redirect($portal->adminPath().'?tab='.$data['kind'])->with('status', 'محتوا ذخیره شد.');
    }

    public function remove(Request $request, string $slug, int $id)
    {
        $portal = $this->portal($request);
        $item = $portal->items()->findOrFail($id);
        $kind = $item->kind;
        $item->delete();
        Audit::record('staff-portal.item-deleted', $item);
        return redirect($portal->adminPath().'?tab='.$kind)->with('status', 'محتوا به زباله‌دان منتقل شد.');
    }

    public function restore(Request $request, string $slug, int $id)
    {
        $portal = $this->portal($request);
        $item = $portal->items()->onlyTrashed()->findOrFail($id);
        $item->restore();
        Audit::record('staff-portal.item-restored', $item);
        return redirect($portal->adminPath().'?tab='.$item->kind)->with('status', 'محتوا بازیابی شد.');
    }

    public function assets(Request $request)
    {
        $request->validate(['q' => 'sometimes|nullable|string|max:150', 'per_page' => 'sometimes|integer|min:1|max:60']);
        $query = $this->portal($request)->assets()->latest('id');
        if ($request->filled('q')) $query->where('original_name', 'like', '%'.$request->input('q').'%');
        return Api::page($query->paginate($request->integer('per_page', 30)));
    }

    public function upload(Request $request)
    {
        $portal = $this->portal($request);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192']);
        $image = StaffPortals::upload($portal, $request->file('file'));
        if ($request->expectsJson()) return Api::ok($image, 'تصویر ذخیره شد.', 201);
        return redirect($portal->adminPath().'?tab=assets')->with('status', 'تصویر ذخیره شد.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()]]);
        if (!Hash::check($data['current_password'], $request->user()->password)) throw ValidationException::withMessages(['current_password' => 'رمز عبور فعلی صحیح نیست.']);
        $request->user()->update(['password' => $data['password']]);
        $request->user()->tokens()->delete();
        Audit::record('auth.password_changed', $request->user());
        return $this->logout($request)->with('status', 'رمز عبور تغییر کرد؛ دوباره وارد شوید.');
    }

    public function image(int $id, string $name)
    {
        abort_unless(StaffPortals::ready(), 404);
        $image = StaffPortalAsset::whereHas('portal', fn ($q) => $q->whereIn('id', StaffPortals::visible()->select('id')))->where('name', $name)->findOrFail($id);
        abort_unless(in_array($image->mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $bytes = base64_decode($image->base64, true);
        abort_if($bytes === false, 404);
        return response($bytes, 200, ['Content-Type' => $image->mime, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400', 'ETag' => '"'.$image->name.'"']);
    }
}

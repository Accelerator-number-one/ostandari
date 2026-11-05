<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Rules\NationalCode;
use App\Services\Audit;
use App\Services\Workspace;
use App\Support\CountySlugs;
use App\Services\OtpService;
use App\Services\RegistrationTerms;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController
{
    private function token(User $user, string $name = 'api'): array
    {
        return ['token' => $user->createToken($name, ['*'], now()->addMinutes((int) config('sanctum.expiration')))->plainTextToken, 'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration') * 60, 'user' => Workspace::user($user)];
    }

    public function login(Request $r)
    {
        $data = $r->validate(['login' => 'required|string|max:255', 'password' => 'required|string|max:200', 'audience' => ['sometimes', Rule::in(['staff', 'citizen'])], 'device_name' => 'sometimes|string|max:60', 'county_slug' => 'sometimes|string|max:80|regex:/^[a-z0-9-]+$/']);
        $user = User::where(fn ($q) => $q->where('email', $data['login'])->orWhere('username', $data['login'])->orWhere('mobile', $data['login']))->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || ! $user->is_active || ! $user->isStaff() && ! $user->mobile_verified_at) {
            throw ValidationException::withMessages(['login' => 'نام کاربری یا رمز عبور صحیح نیست.']);
        }
        if (($data['audience'] ?? null) === 'staff' && ! $user->isStaff()) {
            throw ValidationException::withMessages(['login' => 'این حساب اجازهٔ ورود به پنل مدیریت را ندارد.']);
        }
        if (($data['audience'] ?? null) === 'citizen' && $user->person_type !== 'citizen') {
            throw ValidationException::withMessages(['login' => 'این فرم برای ورود شهروندان است.']);
        }
        if ($user->isStaff()) Workspace::assertUser($user);
        if (isset($data['county_slug'])) {
            Workspace::assertCounty($user, $data['county_slug']);
        }
        $user->update(['last_login_at' => now()]);
        Audit::record('auth.login', $user);

        return Api::ok($this->token($user, $data['device_name'] ?? 'api'));
    }

    public function requestOtp(Request $r, OtpService $otp)
    {
        $data = $r->validate([
            'name' => 'required|string|min:3|max:120',
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'national_code' => ['required', 'string', new NationalCode],
            'terms_accepted' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', Rule::in([RegistrationTerms::VERSION])],
            'terms_document_sha256' => ['required', 'string', Rule::in([RegistrationTerms::documentHash()])],
        ], [
            'terms_accepted.required' => 'پذیرش قوانین و مقررات برای ثبت‌نام الزامی است.',
            'terms_accepted.accepted' => 'برای ادامهٔ ثبت‌نام، قوانین و مقررات پرتال را تأیید کنید.',
            'terms_version.*' => 'نسخهٔ قوانین تغییر کرده است؛ صفحه را تازه‌سازی و متن جدید را مطالعه کنید.',
            'terms_document_sha256.*' => 'متن قوانین تغییر کرده است؛ صفحه را تازه‌سازی و متن جدید را مطالعه کنید.',
        ]);

        return Api::ok($otp->request([
            'name' => $data['name'], 'mobile' => $data['mobile'], 'national_code' => $data['national_code'],
        ], RegistrationTerms::acceptance()), 'کد ورود آماده شد.');
    }

    public function citizenLogin(Request $r, OtpService $otp)
    {
        $data = $r->validate(['mobile' => ['required', 'regex:/^09[0-9]{9}$/']]);
        $user = User::where('mobile', $data['mobile'])->where('person_type', 'citizen')->where('is_active', true)->whereNotNull('mobile_verified_at')->first();
        if (! $user || ! $user->national_code) {
            throw ValidationException::withMessages(['mobile' => 'ابتدا ثبت‌نام و تأیید شمارهٔ همراه را کامل کنید.']);
        }

        return Api::ok($otp->request(['name' => $user->name, 'mobile' => $user->mobile, 'national_code' => $user->national_code]), 'کد ورود ارسال شد.');
    }

    public function verifyOtp(Request $r, OtpService $otp)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'code' => 'required|digits:6', 'device_name' => 'sometimes|string|max:60', 'county_slug' => 'sometimes|string|max:80|regex:/^[a-z0-9-]+$/']);

        return Api::ok($this->token($otp->verify($d['challenge_id'], $d['code']), $d['device_name'] ?? 'citizen'));
    }

    public function me(Request $r)
    {
        if ($r->user()->isStaff()) Workspace::assertUser($r->user());
        return Api::ok(Workspace::user($r->user()));
    }

    public function profile(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['name' => 'sometimes|string|min:3|max:120', 'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users')->ignore($u->id)],
            'county_id' => ['sometimes', 'nullable', Rule::exists('counties', 'id')->whereNull('deleted_at')], 'address' => 'sometimes|nullable|string|max:2000']);
        if (array_key_exists('email', $data) && $data['email'] !== $u->email) {
            $data['email_verified_at'] = null;
        }
        $u->update($data);

        return Api::ok($u->refresh());
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function logoutAll(Request $r)
    {
        $r->user()->tokens()->delete();

        return response()->noContent();
    }

    public function rotate(Request $r)
    {
        $token = $this->token($r->user(), 'rotated');
        $r->user()->currentAccessToken()?->delete();

        return Api::ok($token);
    }

    public function password(Request $r)
    {
        $u = $r->user();
        $d = $r->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()]]);
        abort_unless($u->password && Hash::check($d['current_password'], $u->password), 422, 'رمز عبور فعلی صحیح نیست.');
        $u->update(['password' => $d['password']]);
        $u->tokens()->delete();
        Audit::record('auth.password_changed', $u);

        return Api::ok(null, 'رمز تغییر کرد؛ دوباره وارد شوید.');
    }

    public function forgot(Request $r)
    {
        $d = $r->validate(['email' => 'required|email|max:255']);
        $u = User::where('email', $d['email'])->first();
        $result = [];
        if ($u && $u->is_active && $u->isStaff()) {
            $token = Password::createToken($u);
            Mail::raw("Password reset token: {$token}\nUse POST /api/v1/auth/password/reset. Valid for 60 minutes.", fn ($m) => $m->to($u->email)->subject('Ostandari password reset'));
            if (app()->environment(['local', 'testing']) && config('mail.default') === 'log') {
                $result['dev_token'] = $token;
            }
        }

        return Api::ok($result, 'اگر حسابی با این ایمیل وجود داشته باشد، راهنمای بازیابی ارسال می‌شود.');
    }

    public function reset(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'توکن بازیابی معتبر نیست.']);
        }

        return Api::ok(null,'رمز تغییر کرد.');
    }
}

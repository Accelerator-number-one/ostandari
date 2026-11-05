<?php

namespace App\Services;

use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function request(array $data, ?array $registrationTerms = null): array
    {
        $user = User::where('mobile', $data['mobile'])->orWhere('national_code', $data['national_code'])->first();
        if ($user && $user->person_type !== 'citizen') {
            throw ValidationException::withMessages(['mobile' => 'این شماره برای ورود یا ثبت‌نام شهروندی قابل استفاده نیست.']);
        }
        if ($user && (! $user->is_active || $user->mobile !== $data['mobile'] || $user->national_code !== $data['national_code'])) {
            throw ValidationException::withMessages(['mobile' => 'اطلاعات هویتی با حساب موجود مطابقت ندارد.']);
        }
        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($data, $code, $registrationTerms) {
            OtpChallenge::where('mobile', $data['mobile'])->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $c = new OtpChallenge;
            $c->id = (string) Str::uuid();
            $c->fill([...$data, 'code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds(config('portal.otp.ttl'))]);
            if ($registrationTerms !== null) {
                $c->registration_terms = $registrationTerms;
            }
            $c->save();

            return $c;
        });
        try {
            if (config('portal.otp.driver') === 'http') {
                $endpoint = config('portal.sms.endpoint');
                abort_unless($endpoint && str_starts_with($endpoint, 'https://') && config('portal.sms.token'), 503, 'سرویس پیامک تنظیم نشده است.');
                Http::withToken(config('portal.sms.token'))->timeout(10)->post($endpoint, ['to' => $data['mobile'], 'message' => "کد ورود: {$code}", 'sender' => config('portal.sms.sender')])->throw();
            } else {
                abort_unless(app()->environment(['local', 'testing']), 503, 'ارسال پیامک در این محیط تنظیم نشده است.');
                Log::info('Local OTP', ['challenge_id' => $challenge->id, 'code' => $code]);
            }
        } catch (\Throwable $e) {
            $challenge->update(['consumed_at' => now()]);
            report($e);
            abort(503, 'ارسال کد ممکن نشد. دوباره تلاش کنید.');
        }
        $result = ['challenge_id' => $challenge->id, 'expires_in' => config('portal.otp.ttl')];
        if (app()->environment(['local', 'testing']) && config('portal.otp.driver') === 'log' && config('portal.otp.expose_local')) {
            $result['dev_code'] = $code;
        }

        return $result;
    }

    public function verify(string $id, string $code): User
    {
        // Persist failed attempts before returning an error; never roll them back with an exception.
        $result = DB::transaction(function () use ($id, $code) {
            $challenge = OtpChallenge::lockForUpdate()->find($id);
            if (! $challenge || $challenge->consumed_at || $challenge->expires_at->isPast() || $challenge->attempts >= config('portal.otp.max_attempts')) {
                return null;
            }
            $challenge->increment('attempts');
            if (! Hash::check($code, $challenge->code_hash)) {
                return null;
            }
            $challenge->update(['consumed_at' => now()]);
            $user = User::where('mobile', $challenge->mobile)->lockForUpdate()->first();
            $acceptance = $challenge->registration_terms;
            if (($acceptance !== null || ! $user) && ! RegistrationTerms::isCurrent($acceptance)) {
                return null;
            }
            if (! $user) {
                if (User::where('national_code', $challenge->national_code)->exists()) {
                    return null;
                }
                $user = User::create(['name' => $challenge->name, 'mobile' => $challenge->mobile, 'national_code' => $challenge->national_code,
                    'role_id' => Role::where('name', 'citizen')->firstOrFail()->id, 'person_type' => 'citizen', 'access_scope' => 'organization', 'is_active' => true]);
            }
            if (! $user->is_active || $user->person_type !== 'citizen' || $user->national_code !== $challenge->national_code) {
                return null;
            }
            $user->update(['mobile_verified_at' => now(), 'last_login_at' => now()]);
            if ($acceptance !== null) {
                RegistrationTerms::record($user, $acceptance);
            }

            return $user;
        });
        if (! $result) {
            throw ValidationException::withMessages(['code' => 'کد نامعتبر، منقضی یا مصرف‌شده است.']);
        }

        return $result;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\{EventSubscription, Organization};
use App\Services\EventSms;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, RateLimiter, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class EventSubscriptionController
{
    public function request(Request $request)
    {
        $this->ready();
        $data = $request->validate(['mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'county' => ['nullable', 'string', 'max:80'], 'action' => ['required', Rule::in(['subscribe', 'unsubscribe'])],
            'consent' => ['required_if:action,subscribe', 'accepted_if:action,subscribe']]);
        $organization = null;
        if (! empty($data['county'])) {
            $organization = Organization::active()->where('type', 'county')->whereHas('county', fn ($query) =>
                $query->whereIn('slug', \App\Support\CountySlugs::variants($data['county'])))->first();
            abort_unless($organization, 422, 'فرمانداری انتخاب‌شده معتبر نیست.');
        }
        $hash = hash_hmac('sha256', $data['mobile'], (string) config('app.key'));
        $key = 'event-sms:'.$hash;
        abort_if(RateLimiter::tooManyAttempts($key, 1), 429, 'برای دریافت کد جدید، یک دقیقه صبر کنید.');
        RateLimiter::hit($key, 60);
        $code = (string) random_int(100000, 999999);
        $scope = $organization ? 'county:'.$organization->id : 'province';
        $subscription = DB::transaction(function () use ($hash, $data, $scope, $organization, $code) {
            $row = EventSubscription::firstOrCreate(['mobile_hash' => $hash, 'scope_key' => $scope],
                ['mobile' => $data['mobile'], 'organization_id' => $organization?->id]);
            $row = EventSubscription::lockForUpdate()->findOrFail($row->id);
            $row->update(['challenge_id' => (string) Str::uuid(), 'code_hash' => Hash::make($code),
                'pending_action' => $data['action'], 'code_expires_at' => now()->addMinutes(3), 'attempts' => 0]);
            return $row;
        });
        try {
            EventSms::send($data['mobile'], 'کد تأیید اطلاع‌رسانی رویدادها: '.$code, 'event-verify-'.$subscription->challenge_id);
        } catch (\Throwable $exception) {
            $subscription->update(['code_hash' => null, 'challenge_id' => null]);
            // Provider exceptions can include request bodies; keep phone numbers out of logs.
            abort(503, 'ارسال کد انجام نشد. کمی بعد دوباره تلاش کنید.');
        }
        $result = ['challenge_id' => $subscription->challenge_id, 'expires_in' => 180];
        if (app()->environment(['local', 'testing']) && config('portal.events.driver') === 'log' && config('portal.otp.expose_local')) {
            $result['dev_code'] = $code;
        }
        return Api::ok($result, 'کد تأیید ارسال شد.');
    }

    public function confirm(Request $request)
    {
        $this->ready();
        $data = $request->validate(['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);
        $action = DB::transaction(function () use ($data) {
            $row = EventSubscription::where('challenge_id', $data['challenge_id'])->lockForUpdate()->first();
            if (! $row || ! $row->code_hash || $row->code_expires_at?->isPast() || $row->attempts >= 5) return null;
            $row->increment('attempts');
            if (! Hash::check($data['code'], $row->code_hash)) return null;
            $action = $row->pending_action;
            $row->update(['verified_at' => $action === 'subscribe' ? now() : $row->verified_at,
                'consented_at' => $action === 'subscribe' ? now() : $row->consented_at,
                'unsubscribed_at' => $action === 'unsubscribe' ? now() : null,
                'code_hash' => null, 'challenge_id' => null, 'pending_action' => null, 'code_expires_at' => null]);
            return $action;
        });
        abort_unless($action, 422, 'کد نامعتبر، منقضی یا مصرف‌شده است.');
        return Api::ok(['subscribed' => $action === 'subscribe'], $action === 'subscribe' ? 'عضویت شما ثبت شد.' : 'عضویت پیامکی شما لغو شد.');
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('event_subscriptions') && Schema::hasTable('event_deliveries') && EventSms::ready(), 503, 'خدمات پیامکی موقتاً در دسترس نیست.');
    }
}

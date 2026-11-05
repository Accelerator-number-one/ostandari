<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class EventSms
{
    public static function ready(): bool
    {
        return config('portal.events.driver') === 'http'
            ? str_starts_with((string) config('portal.sms.endpoint'), 'https://') && (bool) config('portal.sms.token')
            : config('portal.events.driver') === 'log' && app()->environment(['local', 'testing']);
    }

    public static function send(string $mobile, string $message, string $idempotencyKey): void
    {
        abort_unless(self::ready(), 503, 'خدمات پیامکی موقتاً در دسترس نیست.');
        if (config('portal.events.driver') === 'http') {
            Http::withToken(config('portal.sms.token'))->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->connectTimeout(3)->timeout(8)->post(config('portal.sms.endpoint'), ['to' => $mobile,
                    'message' => $message, 'sender' => config('portal.sms.sender')])->throw();
        } else {
            Log::info('Local event SMS', ['message' => $message, 'delivery' => $idempotencyKey]);
        }
    }
}

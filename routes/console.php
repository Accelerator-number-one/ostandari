<?php

use App\Models\OtpChallenge;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

Schedule::call(function () {
    OtpChallenge::where('expires_at', '<', now()->subDay())->delete();
    PersonalAccessToken::where('expires_at', '<', now()->subDay())->delete();
})->daily()->name('portal-prune-auth');


\Illuminate\Support\Facades\Artisan::command('portal:send-event-alerts', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('event_deliveries')) {
        $this->error('Run php artisan portal:prepare-events first.');
        return 1;
    }
    \App\Models\Content::visible()->where('type', 'event')->where('published_at', '>=', now()->subDays(7))
        ->orderBy('id')->chunkById(100, fn ($events) => $events->each(fn ($event) => \App\Services\EventAlerts::queue($event)));
    $this->info('Published events queued.');
    return 0;
})->purpose('Send new event notifications and retry pending deliveries.');
Schedule::command('portal:send-event-alerts')->everyMinute()->withoutOverlapping();

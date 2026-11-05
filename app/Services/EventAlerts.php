<?php

namespace App\Services;

use App\Jobs\{PrepareEventAlerts, SendEventSms};
use App\Models\{Content, EventSubscription};
use Illuminate\Support\Facades\{DB, Log, Schema};

final class EventAlerts
{
    public static function queue(Content $content): void
    {
        if ($content->type !== 'event' || $content->status !== 'published') return;
        try {
            PrepareEventAlerts::dispatch($content->id)->afterCommit();
        } catch (\Throwable $exception) {
            Log::warning('Event notification scheduling failed.', ['content_id' => $content->id]);
        }
    }

    public static function prepare(int $contentId): void
    {
        if (! Schema::hasTable('event_deliveries') || ! EventSms::ready()) return;
        $event = Content::visible()->where('type', 'event')->with('organization')->find($contentId);
        if (! $event || $event->organization && ! $event->organization->is_active) return;
        $county = $event->organization?->type === 'county' ? $event->organization_id : null;
        // Subscribe to future publications, without sending historical events on sign-up.
        EventSubscription::where('organization_id', $county)->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at')->where('verified_at', '<=', $event->published_at)
            ->chunkById(200, function ($subscriptions) use ($event) {
                $rows = $subscriptions->map(fn ($row) => ['event_subscription_id' => $row->id, 'content_id' => $event->id,
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now()])->all();
                DB::table('event_deliveries')->insertOrIgnore($rows);
            });
        DB::table('event_deliveries')->where('content_id', $event->id)->where('attempts', '<', 3)
            ->where(fn ($query) => $query->whereIn('status', ['pending', 'failed'])
                ->orWhere(fn ($stale) => $stale->where('status', 'processing')->where('claimed_at', '<', now()->subMinutes(10))))
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(100)->pluck('id')->each(fn ($id) => SendEventSms::dispatch((int) $id)->afterCommit());
    }

    public static function deliver(int $deliveryId): void
    {
        if (! EventSms::ready()) return;
        $delivery = DB::transaction(function () use ($deliveryId) {
            $row = DB::table('event_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if (! $row || in_array($row->status, ['sent', 'skipped'], true) || $row->attempts >= 3
                || $row->status === 'processing' && $row->claimed_at > now()->subMinutes(10)->toDateTimeString()
                || $row->next_attempt_at && $row->next_attempt_at > now()->toDateTimeString()) return null;
            DB::table('event_deliveries')->where('id', $row->id)->update(['status' => 'processing',
                'attempts' => $row->attempts + 1, 'claimed_at' => now(), 'updated_at' => now()]);
            return $row;
        });
        if (! $delivery) return;
        $subscriber = EventSubscription::find($delivery->event_subscription_id);
        $event = Content::visible()->where('type', 'event')->with('organization.county')->find($delivery->content_id);
        $valid = $subscriber?->verified_at && ! $subscriber->unsubscribed_at && $event
            && (! $event->organization || $event->organization->is_active)
            && $subscriber->verified_at->lte($event->published_at)
            && ($event->organization?->type === 'county' ? (int) $event->organization_id === (int) $subscriber->organization_id : $subscriber->organization_id === null);
        if (! $valid) {
            DB::table('event_deliveries')->where('id', $deliveryId)->update(['status' => 'skipped', 'updated_at' => now()]);
            return;
        }
        $prefix = $event->organization?->type === 'county'
            ? '/'.\App\Support\CountySlugs::variants($event->organization->county?->slug ?? '')[0] : '';
        $base = rtrim((string) config('app.url'), '/');
        $message = 'رویداد تازه: '.mb_substr(strip_tags($event->title), 0, 120)."\n".$base.$prefix.'/content/'.$event->id
            ."\nلغو عضویت: ".$base.$prefix.'/events?unsubscribe=1';
        try {
            EventSms::send($subscriber->mobile, $message, 'portal-event-'.$deliveryId);
            DB::table('event_deliveries')->where('id', $deliveryId)->update(['status' => 'sent', 'sent_at' => now(),
                'next_attempt_at' => null, 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('event_deliveries')->where('id', $deliveryId)->update(['status' => 'failed',
                'next_attempt_at' => now()->addMinutes(5 * ($delivery->attempts + 1)), 'updated_at' => now()]);
            Log::warning('Event notification delivery failed.', ['delivery_id' => $deliveryId]);
        }
    }
}

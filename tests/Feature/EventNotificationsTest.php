<?php

namespace Tests\Feature;

use App\Jobs\{PrepareEventAlerts, SendEventSms};
use App\Models\{Content, EventSubscription, Organization, User};
use App\Services\EventAlerts;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Tests\TestCase;

final class EventNotificationsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['portal.events.driver' => 'log', 'portal.otp.expose_local' => true]);
        $this->freezeTime();
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function subscribe(string $mobile = '09120000001', ?string $county = null): array
    {
        return $this->postJson('/web-api/v1/events/subscribe', ['mobile' => $mobile, 'county' => $county,
            'action' => 'subscribe', 'consent' => true])->assertOk()->json('data');
    }

    private function confirm(array $challenge): void
    {
        $this->postJson('/web-api/v1/events/confirm', ['challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['dev_code']])->assertOk()->assertJsonPath('data.subscribed', true);
    }

    private function event(string $title, array $attributes = []): Content
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        return Content::create($attributes + ['type' => 'event', 'title' => $title, 'slug' => str()->slug($title),
            'body' => '<p>شرح رویداد</p>', 'excerpt' => 'خلاصهٔ رویداد', 'locale' => 'fa',
            'organization_id' => $admin->organization_id, 'author_id' => $admin->id,
            'status' => 'published', 'published_at' => now()]);
    }

    private function gateway(): void
    {
        config(['portal.events.driver' => 'http', 'portal.sms.endpoint' => 'https://sms.example.test/send',
            'portal.sms.token' => 'testing-only-gateway-token', 'portal.sms.sender' => '1000']);
    }

    public function test_verified_consent_is_required_numbers_are_encrypted_and_codes_cannot_be_reused(): void
    {
        $this->postJson('/web-api/v1/events/subscribe', ['mobile' => '09120000001', 'action' => 'subscribe'])->assertUnprocessable();
        $challenge = $this->subscribe();
        $row = EventSubscription::firstOrFail();
        $this->assertNull($row->verified_at);
        $this->assertNotSame('09120000001', DB::table('event_subscriptions')->value('mobile'));
        $this->assertSame('09120000001', $row->mobile);
        $this->assertNull(auth()->user());
        $this->confirm($challenge);
        $this->assertNotNull($row->fresh()->verified_at);
        $this->postJson('/web-api/v1/events/confirm', ['challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['dev_code']])->assertUnprocessable();
        $this->postJson('/web-api/v1/events/subscribe', ['mobile' => '09120000001', 'action' => 'subscribe', 'consent' => true])->assertStatus(429);
    }

    public function test_wrong_and_expired_codes_do_not_activate_a_subscription(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $challenge = $this->subscribe('09120000002');
        $wrong = $challenge['dev_code'] === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) $this->postJson('/web-api/v1/events/confirm',
            ['challenge_id' => $challenge['challenge_id'], 'code' => $wrong])->assertUnprocessable();
        $this->assertSame(5, EventSubscription::first()->attempts);
        $this->postJson('/web-api/v1/events/confirm', ['challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['dev_code']])->assertUnprocessable();
        $expired = $this->subscribe('09120000003');
        $this->travel(4)->minutes();
        $this->postJson('/web-api/v1/events/confirm', ['challenge_id' => $expired['challenge_id'],
            'code' => $expired['dev_code']])->assertUnprocessable();
        $this->assertSame(0, EventSubscription::whereNotNull('verified_at')->count());
    }

    public function test_publications_are_scoped_deduplicated_and_future_or_hidden_events_wait(): void
    {
        $this->confirm($this->subscribe());
        $this->confirm($this->subscribe('09120000004', 'amol'));
        $this->subscribe('09120000005'); // Unverified recipient.
        $this->travel(1)->seconds();
        $province = $this->event('Province event');
        $county = $this->event('County event', ['organization_id' => Organization::where('slug', 'county-amol')->value('id')]);
        $draft = $this->event('Draft event', ['status' => 'draft']);
        $future = $this->event('Future event', ['published_at' => now()->addHour()]);
        $this->gateway(); Http::fake(['sms.example.test/*' => Http::response(['accepted' => true])]);
        foreach ([$province, $county, $draft, $future] as $event) EventAlerts::prepare($event->id);
        $this->assertSame(2, DB::table('event_deliveries')->count());
        Queue::assertPushed(SendEventSms::class, 2);
        foreach (DB::table('event_deliveries')->pluck('id') as $id) { EventAlerts::deliver($id); EventAlerts::deliver($id); }
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['to'] === '09120000004' && str_contains($request['message'], '/amol/content/'.$county->id));
        Http::assertSent(fn ($request) => $request['to'] === '09120000001' && str_contains($request['message'], '/content/'.$province->id));
        $this->travel(61)->minutes();
        EventAlerts::prepare($future->id);
        $this->assertSame(3, DB::table('event_deliveries')->count());
        $this->get('/events')->assertOk()->assertSee('خبرم کن')->assertSee('Province event')->assertDontSee('Draft event');
        $this->get('/amol/events')->assertOk()->assertSee('County event')->assertDontSee('Province event');
    }

    public function test_failed_sends_retry_and_cancellation_stops_pending_deliveries(): void
    {
        $this->confirm($this->subscribe()); $this->travel(1)->seconds();
        $event = $this->event('Retry event'); $this->gateway();
        Http::fake(['sms.example.test/*' => Http::sequence()->push([], 500)->push(['accepted' => true], 200)]);
        EventAlerts::prepare($event->id); $id = DB::table('event_deliveries')->value('id');
        EventAlerts::deliver($id); $this->assertDatabaseHas('event_deliveries', ['id' => $id, 'status' => 'failed', 'attempts' => 1]);
        EventAlerts::deliver($id); Http::assertSentCount(1);
        $this->travel(6)->minutes(); EventAlerts::deliver($id); EventAlerts::deliver($id);
        Http::assertSentCount(2); $this->assertDatabaseHas('event_deliveries', ['id' => $id, 'status' => 'sent']);
        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'portal-event-'.$id));
        $pending = $this->event('Pending cancellation event');
        EventAlerts::prepare($pending->id);
        $pendingId = DB::table('event_deliveries')->where('content_id', $pending->id)->value('id');
        config(['portal.events.driver' => 'log']);
        $challenge = $this->postJson('/web-api/v1/events/subscribe', ['mobile' => '09120000001', 'action' => 'unsubscribe'])->assertOk()->json('data');
        $this->postJson('/web-api/v1/events/confirm', ['challenge_id' => $challenge['challenge_id'], 'code' => $challenge['dev_code']])
            ->assertOk()->assertJsonPath('data.subscribed', false);
        $this->assertNotNull(EventSubscription::first()->unsubscribed_at);
        $later = $this->event('Cancelled event'); $this->gateway(); EventAlerts::prepare($later->id);
        $this->assertSame(2, DB::table('event_deliveries')->count());
        EventAlerts::deliver($pendingId);
        $this->assertDatabaseHas('event_deliveries', ['id' => $pendingId, 'status' => 'skipped']);
        Http::assertSentCount(2);
    }

    public function test_setup_is_additive_and_production_never_claims_a_log_driver_sent_sms(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $hash = $admin->password;
        \Illuminate\Support\Facades\Schema::drop('event_deliveries');
        \Illuminate\Support\Facades\Schema::drop('event_subscriptions');
        DB::table('migrations')->where('migration', '2026_10_25_000001_create_event_alert_tables')->delete();
        $this->artisan('portal:prepare-events')->assertSuccessful();
        $this->artisan('portal:prepare-events')->assertSuccessful();
        $this->assertSame($hash, $admin->fresh()->password);
        try {
            $this->app['env'] = 'production';
            $this->postJson('/api/v1/events/subscribe', ['mobile' => '09120000001', 'action' => 'subscribe', 'consent' => true])->assertStatus(503);
            $this->assertSame(0, EventSubscription::count());
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}

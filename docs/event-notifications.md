# Event notifications

The public `/events` page and home section read published `contents` records with type `event`. Existing admin event forms are retained. Notifications require phone verification and explicit opt-in; they do not create citizen accounts. Numbers are encrypted with the application's existing `APP_KEY`. Keep that key stable across deployments.

## Install

```sh
php artisan portal:prepare-events
```

This command runs **only** `2026_10_25_000001_create_event_alert_tables.php`. It creates two new tables, without dropping, seeding or replacing imported portal data. The Vercel container runs this command at startup under a MySQL advisory lock. If the database is temporarily unavailable, the public portal still starts and subscription requests return a temporary-unavailability message. Other hosts should run this command during deployment.

## SMS gateway

Set the following environment variables to a gateway you control:

```dotenv
EVENT_SMS_DRIVER=http
SMS_ENDPOINT=https://your-gateway.example/send
SMS_TOKEN=your-gateway-token
SMS_SENDER=your-sender-number
```

The existing portal SMS integration contract is used: HTTPS POST with Bearer authentication and a JSON body containing `to`, `message`, and `sender`. A successful HTTP response must mean the gateway accepted the message; the gateway should return a non-2xx response on rejection. The gateway must honor the `Idempotency-Key` header to prevent duplicate delivery after an ambiguous network timeout. Provider-specific APIs may require an adapter at this endpoint.

`EVENT_SMS_DRIVER=log` works only in local/testing environments. It never reports a real SMS send in production. A missing provider returns a clear 503 response. No secrets, phone numbers, or confirmation codes are returned in production responses or notification failure logs.

## Queue and scheduler

Immediate publications enqueue notification jobs after the content transaction commits. The existing `sync` queue works for a small preview; use a persistent queue and worker for a production audience. With a configured database queue, for example:

```dotenv
QUEUE_CONNECTION=database
```

```sh
php artisan queue:work --tries=3 --timeout=60
```

Ensure the queue tables for the selected driver already exist. Configure a cron job to run Laravel's scheduler every minute:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

For hosts without a scheduler, run `php artisan portal:send-event-alerts` from an authenticated scheduled console task. This handles future publications when their publication time arrives and retries failed sends (up to three attempts) for events published in the last seven days. Vercel does not automatically run Laravel's scheduler or an enduring queue worker.

Subscriptions receive publications made after verification, in the selected province/county scope. Drafts, future publications, expired/deleted events and inactive organizations are excluded. Delivery is deduplicated per event and subscription. Unsubscribe uses another SMS code so someone else cannot cancel a subscription by knowing its number.

## Checks

`EventNotificationsTest` uses an isolated database and HTTP fakes; it sends no real messages. Tests cover verification, code expiry/reuse/attempt limits, encrypted numbers, province/county separation, scheduling, retries, deduplication and cancellation. Existing content, authentication, image and tenant tests should also pass before release.

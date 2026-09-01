# Orbit Activity module

Starting checkpoint expected before installation:

- Pint: green
- SOS: 12 tests / 83 assertions
- Full regression: 107 tests / 451 assertions

## Scope

This module implements the backend Activity timeline contract:

- authenticated chronological `GET /api/v1/activity/feed`
- cursor pagination, max 50 items per request
- visibility limited to the authenticated user's current Circle memberships
- per-user hide
- per-user report queue for future trust/safety operations
- dashboard preview service: newest 3 visible items from the last 24 hours
- realtime `activity.created` / `activity.removed` fan-out on existing private Circle channels
- Moment publication/removal aggregation
- SOS activation/escalation/resolution aggregation
- Circle join/leave aggregation without patching existing Circle Actions

## Membership integration

`ActivityServiceProvider` adds `TrackCircleMembershipChanges` only to successful mutating Circle API requests. The middleware snapshots the authenticated user's memberships and, when a Circle ID is present in the route, the scoped Circle roster before and after the request. A new membership produces `member.joined`; a removed membership produces `member.left`. This covers self join/leave flows while also supporting future scoped admin membership mutations.

This deliberately avoids editing existing Circles Actions or duplicating Circle authorization/business rules.

## Privacy

Activity stores metadata only. It does not store:

- message plaintext or ciphertext
- Moment plaintext/ciphertext
- encryption keys
- SOS recording content
- raw SOS location
- SOS resolution reason

Moment integration uses an allowlist of safe metadata.

## Dashboard

The product specification places Activity preview inside `GET /dashboard/summary`. That dashboard endpoint is not created here because the existing project does not yet expose a Dashboard backend module. Later Dashboard work should call:

`App\Modules\Activity\Services\ActivityFeedService::dashboardPreview($user)`

It returns at most 3 visible events from the last 24 hours and contains no ad records.

## Ads

The Activity UI may later interleave native ad cards for free-tier users. This backend module intentionally does not persist or inject advertising objects. Monetization/client composition will handle that separately.

## Completion gate

Do not mark Activity complete until all of these are green in the real Orbit project:

1. `vendor\bin\pint --test`
2. `php artisan test tests\Feature\Api\V1\Activity\ActivityTest.php`
3. `php artisan test`
4. `php artisan route:list --path=activity`
5. `php artisan event:list`

# Orbit Milestone 6 event timestamp hotfix

This hotfix fixes the remaining three Milestone 6 feature-test failures.

## Cause
The `billing_promotion_redemptions` and `ad_events` tables are append-only event
tables. Their migration intentionally stores only domain timestamps:

- `billing_promotion_redemptions.redeemed_at`
- `ad_events.occurred_at`

Neither table has Laravel `created_at` / `updated_at` columns, but their Eloquent
models still had timestamps enabled by default. Inserts therefore attempted to
write nonexistent timestamp columns.

## Fix
Set `public $timestamps = false;` on:

- `App\Models\BillingPromotionRedemption`
- `App\Models\AdEvent`

No migration, schema, route, RBAC, billing logic, advertising logic, or SOS
behavior changes are included.

# Orbit Milestone 7 banner/public-header hotfix

This focused hotfix fixes the final three M7 feature-test failures.

## Production fix: system-banner announcement identity
`CampaignService::publishSystemBanner()` used `firstOrCreate(['id' => $campaign->id], ...)`.
`Announcement` deliberately does not make `id` mass assignable, so Eloquent
discarded that UUID and the model's creation hook generated a new one.

The publisher now:
- finds the announcement by the campaign UUID;
- creates an `Announcement` normally when absent;
- explicitly assigns `$announcement->id = $campaign->id`;
- keeps `id` out of `$fillable`;
- preserves the one-to-one campaign/announcement trace ID.

## Test-state fixes: public platform config
Two tests performed authenticated administrator writes immediately before
calling the public `/api/v1/platform/config` endpoint. Laravel feature-test
`withHeaders()` state persisted the admin Authorization header, and Orbit's
two-way consumer/admin token isolation correctly returned HTTP 401.

Those tests now clear Authorization before the public requests. Production
routing and token isolation are unchanged.

No migration, schema, RBAC, maintenance, regional, app-version, SOS, or
notification behavior changes are included.

# Orbit Admin Billing / Payments / Refunds / Advertising — Milestone 6

Starting checkpoint: **313 tests green**.

## Scope
- Free / Orbit Lite / Orbit Plus catalog and entitlements
- historical subscription price snapshots
- admin plan, price, promotion and entitlement operations
- consumer `GET /api/v1/me/subscription`
- subscription change, complimentary grants, extension, cancellation and restoration
- provider-neutral payment reconciliation ledger
- bounded refund workflow with high-risk approval + provider-result reconciliation
- revenue summary (gross/net/refunds/MRR/ARR/plan distribution)
- advertisers, sponsored feed cards and sponsored map pins
- free-tier targeting, frequency caps, hide events and client-event idempotency
- **hard ad suppression while any SOS incident is unresolved**

## Provider boundary
No card numbers, CVV/CVC, payment tokens, provider secrets or credentials are stored by this module. External-provider refunds enter `pending_provider` after approval and require a later provider-result reconciliation. This keeps the backend provider-neutral until Orbit selects/configures actual billing providers.

## Pricing
The sync command creates Free/Lite/Plus plan identities and entitlements but deliberately does **not invent product prices**. Finance must configure actual monthly/annual prices before a paid non-complimentary plan can be assigned.

## Safety
Advertising is never delivered to a user with an unresolved SOS event. This is enforced server-side in `AdvertisingService`, not only in the future UI.

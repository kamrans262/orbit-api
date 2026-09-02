# Orbit Admin Billing / Payments / Refunds / Advertising — M6 Manifest

- Starting verified regression checkpoint: **313 tests green**
- Migration: `2026_09_02_000036_create_billing_advertising_tables.php`
- PHP files in overlay: **59**
- M6 feature/security tests: **31**
- Consumer contracts added: `GET /api/v1/me/subscription`, sponsored feed/map delivery + event recording
- Admin contracts added: plans, prices, entitlements, promotions, subscriptions, payments, refunds, revenue, advertisers, campaigns, creatives
- Sensitive refund approval: Finance Manager only by default; recent admin reauthentication required
- Paid prices: **not hardcoded**; must be configured by Finance
- Payment secrets/card credentials: **not stored**
- SOS advertising rule: unresolved SOS => **zero ads server-side**

## Regression gate
1. migrate
2. sync admin RBAC
3. sync billing catalog
4. Pint / Pint test
5. M6 suite
6. SOS suite
7. Identity suite
8. M5 suite
9. full regression

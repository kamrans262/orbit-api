# Orbit Admin UI implementation plan

The admin UI is grouped into nine implementation milestones around the completed backend domains.

1. **Foundation** — admin shell, dashboard, global search, design system. Existing; refined by M2.
2. **Core Operations** — Users, Devices & Sessions, Circles. **This package.**
3. **Safety / SOS** — active command center, incident history, sensitive-access workflows.
4. **Moderation / Reports / Appeals / Risk** — queues, case detail, enforcement, appeals, risk center.
5. **Support / Privacy / Compliance** — tickets, contact history, privacy requests, deletion/export supervision.
6. **Subscriptions / Payments / Advertising** — plans, subscriptions, payments/refunds, revenue, advertisers/campaigns.
7. **Communications / Content / Runtime Policy** — notifications, announcements, templates, CMS/legal/localization, app versions, maintenance.
8. **Analytics / Feature Flags / System Operations** — analytics/report builder, flags, remote config, health, queues, incidents, integrations/webhooks.
9. **Security / Audit / Administrators / Release Readiness** — security center, IP policy, audit logs, RBAC/admin management, saved views/bulk/export/release gates.

After Milestone 2 is installed, **7 UI milestones remain (M3–M9)**.

Using the recommended sidebar as the module count, **14 top-level modules remain after M2**: Safety/SOS; Moderation & Reports; Support; Subscriptions & Payments; Advertising; Notifications & Announcements; Analytics; Privacy & Compliance; Security; Content; Feature Flags & Configuration; System Operations; Audit Logs; Administrators.

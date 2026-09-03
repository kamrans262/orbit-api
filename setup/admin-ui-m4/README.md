# Orbit Admin UI M4 — Moderation, Appeals & Risk

Canonical M1-M3 admin-console overlay for the already implemented moderation backend. It adds server-paginated report, appeal, and risk surfaces; private notes; assignment and workflow controls; reasoned enforcement with recent reauthentication; second-review appeal separation; and risk signal management. It does not add a second authentication flow, duplicate admin shell, fake data, or plaintext access to E2EE content.

Install with `install-admin-ui-m4.ps1`, then run `verify-admin-ui-m4.ps1 -FullRegression`.


## v4 UTF-8 sidebar repair

The installer uses explicit UTF-8 reads on Windows PowerShell 5.1, repairs sidebar mojibake from the clean pre-M4 backup when present, and the static contract rejects broken UTF-8 text/icons.

## v6 sidebar hardening
The installer deterministically restores the canonical sidebar symbol glyphs by their labels and slightly increases icon size. This avoids Windows PowerShell 5.1 encoding heuristics.

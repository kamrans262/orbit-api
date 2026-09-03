# Orbit Admin UI M2 Foundation Bridge Hotfix

This hotfix fixes the remaining M1 → M2 integration boundary without depending on the Foundation dashboard's Vite entrypoint.

It adds a tiny middleware-injected script at the start of every admin HTML document. The script:

- intercepts Foundation **Users** and **Circles** navigation before the old placeholder toast handler;
- upgrades links where possible;
- captures the administrator Bearer token already used by the dashboard's own API requests into same-tab `sessionStorage` under the M2 key;
- does not bypass backend authentication, permissions, MFA, reauthentication, or audit controls.

The installer backs up `bootstrap/app.php` and any existing target bridge files before changing them.

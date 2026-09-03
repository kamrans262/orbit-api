# Orbit Admin M4 + M5 Single-Auth Runtime Repair v4

This overlay fixes a browser-only authentication divergence in Moderation & Reports (M4) and Support (M5).

## What it fixes

The canonical admin shell already authenticates protected requests through `auth-session.js` and `api-client.js`. A separate M4/M5 compatibility client was also trying to infer credentials from generated browser-storage candidates. The generated candidates included the return URL, theme preference, and cached administrator identity, which are not credentials.

The repair keeps the existing M4/M5 request/route surface intact but forces its bearer token to come from `adminAccessToken()` in the canonical `auth-session.js`. It also makes the generated auth metadata non-authoritative by removing all storage candidates.

## Safety

- Creates timestamped backups before changing runtime files.
- Does not run migrations.
- Does not mutate application data.
- Fails instead of guessing if the expected source signature has drifted.
- Includes a rollback script.
- Runs syntax checks, targeted Admin UI tests, and the frontend build.
- Full regression is optional through the verifier.

## Install

From PowerShell:

```powershell
Expand-Archive "$HOME\Downloads\orbit_api-admin-ui-m4-m5-single-auth-repair-v4.zip" "$HOME\Downloads\orbit_api-admin-ui-m4-m5-single-auth-repair-v4" -Force
Copy-Item "$HOME\Downloads\orbit_api-admin-ui-m4-m5-single-auth-repair-v4\orbit_api\setup\admin-ui-single-auth-repair-v4" "C:\laravel-projects\orbit_api\setup" -Recurse -Force

Set-Location "C:\laravel-projects\orbit_api"
Set-ExecutionPolicy -Scope Process Bypass -Force
.\setup\admin-ui-single-auth-repair-v4\install-admin-ui-single-auth-repair-v4.ps1
```

For the full release gate:

```powershell
.\setup\admin-ui-single-auth-repair-v4\verify-admin-ui-single-auth-repair-v4.ps1 -FullRegression
```

## Browser acceptance

After the installer succeeds:

1. Use the admin profile menu to sign out once.
2. Sign in again through `/admin/login` and complete MFA.
3. Hard-refresh the browser (`Ctrl+Shift+R`).
4. Open **Moderation & Reports** and **Support**.

A successful queue response is the final acceptance criterion. Static/PHP tests alone cannot prove that the browser sent the correct live Authorization header.

## Rollback

The installer prints a backup path such as:

`C:\laravel-projects\orbit_api\storage\app\orbit-admin-m4-m5-single-auth-backups\YYYYMMDDHHMMSS`

Restore it with:

```powershell
.\setup\admin-ui-single-auth-repair-v4\rollback-admin-ui-single-auth-repair-v4.ps1 -BackupPath "PASTE_THE_BACKUP_PATH_HERE"
```

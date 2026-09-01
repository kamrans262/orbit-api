# Orbit Admin Foundation installer path hotfix

This hotfix replaces only:

`setup/admin-foundation/install-admin-foundation.ps1`

Reason:
The original installer used relative paths with .NET `WriteAllText()`. On Windows,
the .NET process working directory may remain `C:\Windows\System32` even when
PowerShell's current location is the Laravel project. This caused the provider
write to target `C:\Windows\System32\bootstrap\providers.php`.

The replacement resolves all project files to absolute paths using
`(Get-Location).ProviderPath` and remains idempotent. It is safe to run after the
original installer partially added the admin route/console require lines.

# Orbit Admin Safety / SOS Command Center — Milestone 3 Manifest

## Required starting checkpoint

- Pint: 580 files green
- Admin Foundation: 23 tests / 111 assertions
- Admin Core Operations: 31 tests / 143 assertions
- SOS release-blocker: 12 tests / 83 assertions
- Full regression: 211 tests / 951 assertions

## Package summary

- PHP files: 35
- Milestone 3 tests: 24
- Migration: `2026_09_02_000033_create_admin_sos_operations_tables.php`
- New provider: `App\Providers\AdminSafetyServiceProvider`
- New admin route file: `routes/admin_sos.php`
- New admin broadcast channel file: `routes/channels_admin_sos.php`
- New schedule file: `routes/console_admin_sos.php`
- Replaced RBAC definition: `app/Modules/Admin/Services/AdminRbacService.php`

## New permission slugs

- `sos.view`
- `sos.manage`
- `sos.export`
- `sos.location.access`
- `sos.recordings.access`
- `sos.sensitive.audit`

`Super Administrator` receives normal SOS operations permissions but does not automatically receive precise location or recording access. `Senior Safety Operator` receives the separately permissioned sensitive safety capabilities by default.

## Important boundaries

- Consumer SOS state remains authoritative.
- Admin operational closure does not resolve a consumer SOS.
- Normal admin detail/export does not expose precise coordinates or the recording reference.
- Sensitive endpoints require separate permission, recent reauthentication, purpose, reason, and immutable access/audit records.
- The recording endpoint returns only an opaque encrypted reference, never plaintext or decryption keys.
- Admin realtime events contain safe metadata only.
- Client network health and country/region are not fabricated when the current backend has no trusted telemetry source.

## Files

- `app/Console/Commands/PurgeExpiredAdminSosExportsCommand.php`
- `app/Models/AdminSosExport.php`
- `app/Models/AdminSosIncidentControl.php`
- `app/Models/AdminSosNote.php`
- `app/Models/AdminSosSensitiveAccess.php`
- `app/Modules/Admin/Safety/Events/AdminSosIncidentUpdated.php`
- `app/Modules/Admin/Safety/Http/Controllers/AccessAdminSosLocationController.php`
- `app/Modules/Admin/Safety/Http/Controllers/AccessAdminSosRecordingController.php`
- `app/Modules/Admin/Safety/Http/Controllers/AddAdminSosNoteController.php`
- `app/Modules/Admin/Safety/Http/Controllers/AdminRealtimeAuthController.php`
- `app/Modules/Admin/Safety/Http/Controllers/CreateAdminSosExportController.php`
- `app/Modules/Admin/Safety/Http/Controllers/ListAdminSosIncidentsController.php`
- `app/Modules/Admin/Safety/Http/Controllers/ListAdminSosSensitiveAccessController.php`
- `app/Modules/Admin/Safety/Http/Controllers/ShowAdminSosIncidentController.php`
- `app/Modules/Admin/Safety/Http/Controllers/UpdateAdminSosAssignmentController.php`
- `app/Modules/Admin/Safety/Http/Controllers/UpdateAdminSosClassificationController.php`
- `app/Modules/Admin/Safety/Http/Requests/AddAdminSosNoteRequest.php`
- `app/Modules/Admin/Safety/Http/Requests/AdminSosSensitiveAccessRequest.php`
- `app/Modules/Admin/Safety/Http/Requests/CreateAdminSosExportRequest.php`
- `app/Modules/Admin/Safety/Http/Requests/ListAdminSosRequest.php`
- `app/Modules/Admin/Safety/Http/Requests/UpdateAdminSosAssignmentRequest.php`
- `app/Modules/Admin/Safety/Http/Requests/UpdateAdminSosClassificationRequest.php`
- `app/Modules/Admin/Safety/Listeners/BroadcastAdminSosLifecycleUpdate.php`
- `app/Modules/Admin/Safety/Services/AdminSosDirectoryService.php`
- `app/Modules/Admin/Safety/Services/AdminSosOperationsService.php`
- `app/Modules/Admin/Safety/Services/AdminSosPresenter.php`
- `app/Modules/Admin/Safety/Services/AdminSosRealtimeService.php`
- `app/Modules/Admin/Safety/Services/AdminSosSensitiveAccessService.php`
- `app/Modules/Admin/Services/AdminRbacService.php`
- `app/Providers/AdminSafetyServiceProvider.php`
- `database/migrations/2026_09_02_000033_create_admin_sos_operations_tables.php`
- `routes/admin_sos.php`
- `routes/channels_admin_sos.php`
- `routes/console_admin_sos.php`
- `setup/admin-safety/README.md`
- `setup/admin-safety/install-admin-safety.ps1`
- `setup/admin-safety/verify-admin-safety.ps1`
- `tests/Feature/Api/Admin/V1/AdminSosCommandCenterTest.php`

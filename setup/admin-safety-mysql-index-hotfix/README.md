# Orbit Admin Safety M3 MySQL index-name hotfix

This hotfix replaces only:

`database/migrations/2026_09_02_000033_create_admin_sos_operations_tables.php`

## Cause

MySQL limits identifiers to 64 characters. Laravel's automatically generated
composite index name for:

`admin_sos_incident_controls (assigned_admin_id, operational_status)`

was 70 characters and caused migration 000033 to fail.

## Resume safety

MySQL DDL may leave the first table created even when the migration is not
recorded as completed. The replacement migration is idempotent for this exact
partial state:

- detects an existing `admin_sos_incident_controls` table,
- adds the missing composite index using the short explicit name
  `admin_sos_ctrl_assign_status_idx`,
- creates the remaining M3 tables if they are missing,
- uses short explicit names for all composite indexes.

After copying this hotfix into the project, rerun:

`php artisan migrate`

Do not manually drop the partially created table.

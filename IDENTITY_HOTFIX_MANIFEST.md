# Identity Hotfix Manifest

Files replaced/added:
- `tests/Feature/Api/V1/Identity/IdentityTest.php`
- `database/migrations/2026_09_02_000030_add_device_name_to_devices_table.php`
- `setup/identity-hotfix/README.md`

Purpose: correct multi-user bearer-auth isolation in the Laravel feature test process and add the device-name schema field required by the rename API.

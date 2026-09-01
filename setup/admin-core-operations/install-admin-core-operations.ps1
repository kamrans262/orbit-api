$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath

if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

$required = @(
    (Join-Path $projectRoot 'app\Models\AdminUser.php'),
    (Join-Path $projectRoot 'app\Providers\AdminServiceProvider.php'),
    (Join-Path $projectRoot 'routes\admin.php'),
    (Join-Path $projectRoot 'database\migrations\2026_09_02_000031_create_admin_platform_foundation_tables.php'),
    (Join-Path $projectRoot 'database\migrations\2026_09_02_000032_create_admin_core_operations_tables.php')
)

foreach ($path in $required) {
    if (-not (Test-Path $path)) {
        throw "Required Orbit Admin file not found: $path"
    }
}

Push-Location $projectRoot
try {
    php artisan optimize:clear
}
finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Orbit Admin Core Operations Milestone 2 overlay is wired.'
Write-Host 'Next run one command at a time:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan orbit:admin:sync-rbac'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php'
Write-Host '  php artisan test tests\Feature\Api\V1\Sos\SosTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=admin/v1/users'
Write-Host '  php artisan route:list --path=admin/v1/circles'

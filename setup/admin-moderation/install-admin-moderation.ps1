$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$channelRoutes = Join-Path $projectRoot 'routes\channels.php'
foreach ($path in @($apiRoutes, $channelRoutes)) {
    if (-not (Test-Path $path)) { throw "Required Orbit file not found: $path" }
    $backup = "$path.pre-admin-moderation-m4-backup"
    if (-not (Test-Path $backup)) { Copy-Item $path $backup -Force }
}

function Add-OrbitRequireLine {
    param([Parameter(Mandatory = $true)][string]$Path,[Parameter(Mandatory = $true)][string]$Line)
    if (-not (Select-String -Path $Path -Pattern $Line -SimpleMatch -Quiet)) {
        Add-Content -Path $Path -Value "`r`n$Line"
    }
}

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/moderation.php';"
Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/admin_moderation.php';"
Add-OrbitRequireLine -Path $channelRoutes -Line "require __DIR__.'/channels_admin_moderation.php';"

Push-Location $projectRoot
try { php artisan optimize:clear } finally { Pop-Location }

Write-Host ''
Write-Host 'Orbit Admin Moderation / Appeals / Risk Milestone 4 wiring installed.'
Write-Host 'Next run one command at a time:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan orbit:admin:sync-rbac'
Write-Host '  php artisan orbit:moderation:import-activity-reports'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminModerationAppealsRiskTest.php'
Write-Host '  php artisan test tests\Feature\Api\V1\Activity\ActivityTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminSosCommandCenterTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php'
Write-Host '  php artisan test tests\Feature\Api\V1\Sos\SosTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=admin/v1/reports'
Write-Host '  php artisan route:list --path=admin/v1/appeals'
Write-Host '  php artisan route:list --path=admin/v1/risk'

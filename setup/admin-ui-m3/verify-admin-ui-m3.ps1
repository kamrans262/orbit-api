param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
$setupRoot = $PSScriptRoot

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this verifier from the Orbit Laravel project root (the directory containing artisan).'
}

function Invoke-Gate {
    param(
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][scriptblock]$Command
    )
    Write-Host ''
    Write-Host "== $Label =="
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed with exit code $LASTEXITCODE" }
}

$required = @(
    'resources\views\admin\layouts\app.blade.php',
    'resources\views\admin\operations\sos\index.blade.php',
    'resources\views\admin\operations\sos\show.blade.php',
    'resources\js\admin-console\sos.js',
    'resources\js\admin-console\sos-contract.js',
    'tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php'
)
foreach ($path in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $path))) { throw "Missing M3 file: $path" }
}

$layout = [System.IO.File]::ReadAllText((Join-Path $projectRoot 'resources\views\admin\layouts\app.blade.php'))
if (-not $layout.Contains('data-orbit-canonical-shell="v1"') -or -not $layout.Contains('data-orbit-auth-owner="foundation"')) {
    throw 'Canonical single-shell / Foundation-auth ownership invariant is not present.'
}

# First prove the route resolver against the exact current Orbit route fixture.
& node (Join-Path $setupRoot 'tests\sos-route-generator.mjs')
if ($LASTEXITCODE -ne 0) { throw 'M3 SOS route-contract resolver self-test failed.' }

# Rebuild the expected browser route contract from the checkout's current backend
# route inventory and require an exact match with the installed contract.
$tempContract = Join-Path ([System.IO.Path]::GetTempPath()) ('orbit-sos-contract-verify-' + [guid]::NewGuid().ToString('N') + '.js')
try {
    & (Join-Path $setupRoot 'generate-sos-contract.ps1') -OutputPath $tempContract
    if ($LASTEXITCODE -ne 0) { throw 'Admin SOS backend route contract validation failed.' }
    $expectedHash = (Get-FileHash -LiteralPath $tempContract -Algorithm SHA256).Hash
    $actualHash = (Get-FileHash -LiteralPath (Join-Path $projectRoot 'resources\js\admin-console\sos-contract.js') -Algorithm SHA256).Hash
    if ($expectedHash -ne $actualHash) { throw 'resources/js/admin-console/sos-contract.js does not match the currently registered backend route inventory. Re-run the M3 installer.' }
} finally {
    if (Test-Path -LiteralPath $tempContract) { Remove-Item -LiteralPath $tempContract -Force -ErrorAction SilentlyContinue }
}

Set-Location $projectRoot
Invoke-Gate -Label 'Laravel cache clear' -Command { & php artisan optimize:clear }

$pintBat = Join-Path $projectRoot 'vendor\bin\pint.bat'
$pint = Join-Path $projectRoot 'vendor\bin\pint'
if (Test-Path -LiteralPath $pintBat) {
    Invoke-Gate -Label 'Pint static style gate (project canonical discovery)' -Command { & $pintBat --test }
} elseif (Test-Path -LiteralPath $pint) {
    Invoke-Gate -Label 'Pint static style gate (project canonical discovery)' -Command { & $pint --test }
} else {
    throw 'Laravel Pint executable was not found under vendor\bin.'
}

$dashboardContract = Join-Path $projectRoot 'setup\admin-ui-consolidation\tests\dashboard-contract.mjs'
if (Test-Path -LiteralPath $dashboardContract) {
    Invoke-Gate -Label 'M1 dashboard response-contract regression' -Command { & node setup/admin-ui-consolidation/tests/dashboard-contract.mjs }
}
Invoke-Gate -Label 'M3 SOS browser privacy / architecture contract' -Command { & node setup/admin-ui-m3/tests/sos-ui-contract.mjs }
Invoke-Gate -Label 'Production Vite build' -Command { & npm run build }
Invoke-Gate -Label 'Canonical M1-M3 admin console architecture' -Command { & php artisan test tests\Feature\AdminUi\AdminConsoleConsolidationTest.php }
Invoke-Gate -Label 'Milestone 3 SOS UI regression' -Command { & php artisan test tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php }
Invoke-Gate -Label 'Admin Foundation UI regression' -Command { & php artisan test tests\Feature\AdminUi\AdminUiFoundationTest.php }
Invoke-Gate -Label 'Admin SOS backend regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminSosCommandCenterTest.php }
Invoke-Gate -Label 'Admin Core Operations regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php }
Invoke-Gate -Label 'Admin Foundation security regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php }
Invoke-Gate -Label 'Consumer SOS regression' -Command { & php artisan test tests\Feature\Api\V1\Sos\SosTest.php }

$m9Test = Join-Path $projectRoot 'tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php'
if (Test-Path -LiteralPath $m9Test) {
    Invoke-Gate -Label 'Dashboard and global-search backend regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php }
}

Invoke-Gate -Label 'Canonical SOS web route inventory' -Command { & php artisan route:list --path=admin/operations/sos }
Invoke-Gate -Label 'Admin SOS API route inventory' -Command { & php artisan route:list --path=admin/v1/sos }

if ($FullRegression) {
    Invoke-Gate -Label 'Full Laravel regression suite' -Command { & php artisan test }
}

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 3 targeted verification passed.'
if (-not $FullRegression) {
    Write-Host 'For the release gate, also run:'
    Write-Host '  .\setup\admin-ui-m3\verify-admin-ui-m3.ps1 -FullRegression'
}

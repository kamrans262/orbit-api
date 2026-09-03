[CmdletBinding()]
param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this verifier from the Orbit Laravel project root.'
}

$verifier = Join-Path $PSScriptRoot 'verify-single-auth-v4.mjs'
$files = @(
    'resources\js\admin-console\admin-api-client.js',
    'resources\js\admin-console\admin-auth.generated.js',
    'resources\js\admin-console\auth-session.js',
    'resources\js\admin-console\api-client.js',
    'resources\js\admin-console\moderation-m4.js',
    'resources\js\admin-console\support-m5.js'
)

& node $verifier $projectRoot
if ($LASTEXITCODE -ne 0) { throw 'Static single-auth verification failed.' }

foreach ($file in $files) {
    $path = Join-Path $projectRoot $file
    & node --check $path
    if ($LASTEXITCODE -ne 0) { throw "node --check failed for $file" }
}

& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'php artisan optimize:clear failed.' }

$tests = @(
    'tests\Feature\AdminUi\AdminRuntimeIntegrationContractTest.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
    'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php',
    'tests\Feature\AdminUi\AdminModerationReportsUiTest.php',
    'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php',
    'tests\Feature\AdminUi\AdminSupportManagementUiTest.php'
)
foreach ($test in $tests) {
    if (Test-Path (Join-Path $projectRoot $test)) {
        & php artisan test $test
        if ($LASTEXITCODE -ne 0) { throw "Targeted test failed: $test" }
    }
}

if (Test-Path (Join-Path $projectRoot 'package.json')) {
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build failed.' }
}

if ($FullRegression) {
    & vendor\bin\pint --test
    if ($LASTEXITCODE -ne 0) { throw 'Pint regression failed.' }

    & php artisan test
    if ($LASTEXITCODE -ne 0) { throw 'Full Laravel regression failed.' }
}

Write-Host ''
Write-Host 'Orbit M4 + M5 single-auth verification passed.' -ForegroundColor Green
if (-not $FullRegression) {
    Write-Host 'For the complete Laravel gate run again with -FullRegression.'
}
Write-Host 'A live browser acceptance check is still required for Moderation & Reports and Support.'

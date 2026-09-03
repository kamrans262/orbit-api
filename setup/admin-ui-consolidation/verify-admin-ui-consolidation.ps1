param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this verification script from the Orbit Laravel project root (the directory containing artisan).'
}

function Assert-Exists {
    param([Parameter(Mandatory = $true)][string]$RelativePath)
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $RelativePath))) {
        throw "Expected canonical file is missing: $RelativePath"
    }
}

function Assert-Absent {
    param([Parameter(Mandatory = $true)][string]$RelativePath)
    if (Test-Path -LiteralPath (Join-Path $projectRoot $RelativePath)) {
        throw "Obsolete M2 architecture is still present: $RelativePath"
    }
}

function Assert-TextContains {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][string]$Text
    )
    $content = [System.IO.File]::ReadAllText((Join-Path $projectRoot $RelativePath))
    if (-not $content.Contains($Text)) {
        throw "Expected '$Text' in $RelativePath"
    }
}

function Assert-TextExcludes {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][string]$Text
    )
    $content = [System.IO.File]::ReadAllText((Join-Path $projectRoot $RelativePath))
    if ($content.Contains($Text)) {
        throw "Obsolete reference '$Text' is still present in $RelativePath"
    }
}

function Invoke-Gate {
    param(
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][scriptblock]$Command
    )

    Write-Host ''
    Write-Host "== $Label =="
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE"
    }
}

$canonicalPaths = @(
    'resources\css\admin-console.css',
    'resources\js\admin-console\index.js',
    'resources\js\admin-console\api-client.js',
    'resources\js\admin-console\foundation-auth-keys.js',
    'resources\js\admin-console\auth-session.js',
    'resources\js\admin-console\shell.js',
    'resources\js\admin-console\dashboard.js',
    'resources\js\admin-console\users.js',
    'resources\js\admin-console\circles.js',
    'resources\js\admin-console\ui.js',
    'resources\views\admin\layouts\app.blade.php',
    'resources\views\admin\partials\sidebar.blade.php',
    'resources\views\admin\partials\topbar.blade.php',
    'resources\views\admin\dashboard.blade.php',
    'resources\views\admin\operations\users\index.blade.php',
    'resources\views\admin\operations\users\show.blade.php',
    'resources\views\admin\operations\circles\index.blade.php',
    'resources\views\admin\operations\circles\show.blade.php',
    'routes\admin_console.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
    'tests\Feature\AdminUi\AdminUiFoundationTest.php',
    'setup\admin-ui-consolidation\tests\dashboard-contract.mjs'
)
foreach ($path in $canonicalPaths) { Assert-Exists -RelativePath $path }

$legacyPaths = @(
    'resources\views\admin\operations\layouts\shell.blade.php',
    'resources\css\admin-ui-m2.css',
    'resources\js\admin-ui-m2',
    'routes\admin_ui_m2.php',
    'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php',
    'public\orbit-admin-m2-foundation-bridge.js',
    'tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
)
foreach ($path in $legacyPaths) { Assert-Absent -RelativePath $path }

Assert-TextContains -RelativePath 'routes\web.php' -Text "require __DIR__.'/admin_console.php';"
Assert-TextExcludes -RelativePath 'routes\web.php' -Text 'admin_ui_m2.php'
Assert-TextExcludes -RelativePath 'bootstrap\app.php' -Text 'InjectAdminUiM2FoundationBridge'
Assert-TextExcludes -RelativePath 'resources\css\app.css' -Text 'admin-ui-m2.css'
Assert-TextExcludes -RelativePath 'resources\js\app.js' -Text 'admin-ui-m2'
Assert-TextContains -RelativePath 'vite.config.js' -Text 'resources/css/admin-console.css'
Assert-TextContains -RelativePath 'vite.config.js' -Text 'resources/js/admin-console/index.js'
Assert-TextContains -RelativePath 'resources\views\admin\layouts\app.blade.php' -Text 'data-orbit-canonical-shell="v1"'
Assert-TextContains -RelativePath 'resources\views\admin\layouts\app.blade.php' -Text 'data-orbit-auth-owner="foundation"'
Assert-TextContains -RelativePath 'resources\views\admin\layouts\app.blade.php' -Text 'data-auth-gate'
Assert-TextExcludes -RelativePath 'resources\views\admin\layouts\app.blade.php' -Text 'data-admin-auth-dialog'
Assert-TextExcludes -RelativePath 'resources\views\admin\layouts\app.blade.php' -Text 'data-admin-auth-form'
Assert-TextContains -RelativePath 'resources\js\admin-console\shell.js' -Text "const LOGIN_PATH = '/admin/login';"
Assert-TextContains -RelativePath 'resources\js\admin-console\shell.js' -Text "adminApi('/api/admin/v1/auth/me')"
Assert-TextExcludes -RelativePath 'resources\js\admin-console\shell.js' -Text '/api/admin/v1/auth/login'
Assert-TextExcludes -RelativePath 'resources\js\admin-console\shell.js' -Text '/api/admin/v1/auth/mfa/verify'
Assert-TextExcludes -RelativePath 'resources\js\admin-console\shell.js' -Text 'writeAdminSession'
Assert-TextContains -RelativePath 'resources\js\admin-console\auth-session.js' -Text 'FOUNDATION_TOKEN_KEYS'
Assert-TextContains -RelativePath 'resources\js\admin-console\auth-session.js' -Text 'FOUNDATION_DETECTED_TOKEN_KEYS'
Assert-TextExcludes -RelativePath 'resources\js\admin-console\auth-session.js' -Text 'window.fetch'
Assert-TextExcludes -RelativePath 'resources\js\admin-console\auth-session.js' -Text 'XMLHttpRequest'
Assert-TextExcludes -RelativePath 'resources\js\admin-console\api-client.js' -Text "throw new OrbitAdminApiError('Administrator authentication is required.'"
Assert-TextContains -RelativePath 'resources\js\admin-console\dashboard.js' -Text 'data?.snapshot'
Assert-TextContains -RelativePath 'resources\js\admin-console\dashboard.js' -Text 'business.users.total'
Assert-TextContains -RelativePath 'resources\js\admin-console\dashboard.js' -Text 'dashboard_contract_mismatch'
Assert-TextExcludes -RelativePath 'tests\Feature\AdminUi\AdminUiFoundationTest.php' -Text 'Search users, Circles, incidents'
Assert-TextExcludes -RelativePath 'tests\Feature\AdminUi\AdminUiFoundationTest.php' -Text 'admin-ui/js/pages/dashboard.js'
Assert-TextContains -RelativePath 'tests\Feature\AdminUi\AdminUiFoundationTest.php' -Text 'Search Orbit administration'
Assert-TextContains -RelativePath 'tests\Feature\AdminUi\AdminUiFoundationTest.php' -Text 'resources/js/admin-console/index.js'

Set-Location $projectRoot

Invoke-Gate -Label 'Laravel cache clear' -Command { & php artisan optimize:clear }

$pintBat = Join-Path $projectRoot 'vendor\bin\pint.bat'
$pint = Join-Path $projectRoot 'vendor\bin\pint'

# Rollback checkpoints live under storage/app and therefore sit outside Pint's
# normal project discovery. Use the project's canonical no-path Pint invocation so
# existing pint.json/.gitignore/default finder rules remain authoritative. Passing
# broad source directories here changes Pint discovery semantics and can lint files
# intentionally excluded by the project, producing false failures unrelated to M1/M2.
if (Test-Path -LiteralPath $pintBat) {
    Invoke-Gate -Label 'Pint static style gate (project canonical discovery)' -Command { & $pintBat --test }
} elseif (Test-Path -LiteralPath $pint) {
    Invoke-Gate -Label 'Pint static style gate (project canonical discovery)' -Command { & $pint --test }
} else {
    throw 'Laravel Pint executable was not found under vendor\bin.'
}

Invoke-Gate -Label 'Dashboard response-contract adapter' -Command { & node setup/admin-ui-consolidation/tests/dashboard-contract.mjs }
Invoke-Gate -Label 'Production Vite build' -Command { & npm run build }
Invoke-Gate -Label 'Canonical admin UI consolidation tests' -Command { & php artisan test tests\Feature\AdminUi\AdminConsoleConsolidationTest.php }
Invoke-Gate -Label 'Admin Foundation UI regression' -Command { & php artisan test tests\Feature\AdminUi\AdminUiFoundationTest.php }
Invoke-Gate -Label 'Admin Core Operations regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php }
Invoke-Gate -Label 'Admin Foundation security regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php }

$m9Test = Join-Path $projectRoot 'tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php'
if (Test-Path -LiteralPath $m9Test) {
    Invoke-Gate -Label 'Admin dashboard and global-search backend regression' -Command { & php artisan test tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php }
}

Invoke-Gate -Label 'Canonical admin operation route inventory' -Command { & php artisan route:list --path=admin/operations }

if ($FullRegression) {
    Invoke-Gate -Label 'Full Laravel regression suite' -Command { & php artisan test }
}

Write-Host ''
Write-Host 'Orbit Admin UI M1 + M2 consolidation verification passed.'
if (-not $FullRegression) {
    Write-Host 'Targeted gate passed. For the release gate, also run:'
    Write-Host '  .\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1 -FullRegression'
}

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Run-Step([string]$Label, [scriptblock]$Command) {
    Write-Host "`n== $Label ==" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed with exit code $LASTEXITCODE" }
}

Run-Step 'Laravel cache clear' { php artisan optimize:clear | Out-Host }
Run-Step 'Recovered sidebar / shared-shell contract' { node '.\setup\admin-ui-m4\verify-admin-ui-m4-recovery.mjs' | Out-Host }

if (Test-Path '.\setup\admin-ui-m1\verify-dashboard-response-contract.mjs') {
    Run-Step 'Dashboard response-contract regression' { node '.\setup\admin-ui-m1\verify-dashboard-response-contract.mjs' | Out-Host }
}

Run-Step 'Production Vite build' { npm run build | Out-Host }
Run-Step 'Canonical admin UI regressions' { php artisan test tests\Feature\AdminUi\AdminConsoleConsolidationTest.php tests\Feature\AdminUi\AdminUiFoundationTest.php tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php tests\Feature\AdminUi\AdminModerationReportsUiTest.php | Out-Host }
Run-Step 'Dashboard API regression' { php artisan test tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php | Out-Host }
Run-Step 'Moderation backend regression' { php artisan test tests\Feature\Api\Admin\V1\AdminModerationAppealsRiskTest.php | Out-Host }

Write-Host "`nOrbit M4 recovery verification passed." -ForegroundColor Green

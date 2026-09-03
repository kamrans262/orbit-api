param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Run-Step([string]$Label, [scriptblock]$Command) {
    Write-Host "`n== $Label ==" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed with exit code $LASTEXITCODE" }
}

Run-Step 'Laravel cache clear' { php artisan optimize:clear | Out-Host }
Run-Step 'M4 zero-placeholder / canonical-layout contract' { node '.\setup\admin-ui-m4\verify-m4-runtime-v9.mjs' | Out-Host }
Run-Step 'Blade compilation' { php artisan view:cache | Out-Host }
Run-Step 'M4 six-route rendering smoke test' { php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host }
Run-Step 'M1-M4 admin UI regressions' { php artisan test tests\Feature\AdminUi\AdminConsoleConsolidationTest.php tests\Feature\AdminUi\AdminUiFoundationTest.php tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php tests\Feature\AdminUi\AdminModerationReportsUiTest.php tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host }
Run-Step 'Moderation backend regression' { php artisan test tests\Feature\Api\Admin\V1\AdminModerationAppealsRiskTest.php | Out-Host }
Run-Step 'Production Vite build' { npm run build | Out-Host }
Run-Step 'Pint static style gate' { vendor\bin\pint --test | Out-Host }

if ($FullRegression) {
    Run-Step 'Full Laravel regression suite' { php artisan test | Out-Host }
}

Write-Host "`nOrbit M4 runtime recovery verification passed." -ForegroundColor Green

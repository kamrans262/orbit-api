param([switch]$FullRegression)
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
function Run([string]$Label,[scriptblock]$Command){Write-Host "`n== $Label =="; & $Command; if($LASTEXITCODE -ne 0){throw "$Label failed with exit code $LASTEXITCODE"}}
Run 'Laravel cache clear' { php artisan optimize:clear }
Run 'M4 browser privacy / architecture contract' { node setup/admin-ui-m4/verify-moderation-ui-contract.mjs }
Run 'Pint static style gate' { vendor\bin\pint --test }
Run 'Production Vite build' { npm run build }
Run 'Milestone 4 Moderation UI regression' { php artisan test tests/Feature/AdminUi/AdminModerationReportsUiTest.php }
Run 'Moderation / Appeals / Risk backend regression' { php artisan test tests/Feature/Api/Admin/V1/AdminModerationAppealsRiskTest.php }
Run 'Canonical M1-M3 architecture regression' { php artisan test tests/Feature/AdminUi/AdminConsoleConsolidationTest.php tests/Feature/AdminUi/AdminSosCommandCenterUiTest.php }
if($FullRegression){Run 'Full Laravel regression suite' { php artisan test }}
Write-Host "`nOrbit Admin UI Milestone 4 verification passed." -ForegroundColor Green

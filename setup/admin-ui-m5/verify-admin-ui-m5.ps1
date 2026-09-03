param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Run([string]$Label, [scriptblock]$Command) {
    Write-Host ''
    Write-Host "== $Label ==" -ForegroundColor Cyan
    & $Command | Out-Host
    if ($LASTEXITCODE -ne 0) { throw "$Label failed." }
}

Run 'M5 static UI contract' { node .\setup\admin-ui-m5\verify-support-ui-contract.mjs $root }
Run 'Laravel cache refresh' { php artisan optimize:clear }
Run 'M5 web route inventory' { php artisan route:list --path=admin/operations/support }
Run 'Support backend route inventory' { php artisan route:list --path=admin/v1/support }
Run 'Pint regression' { vendor\bin\pint --test }
Run 'Frontend production build' { npm run build }
Run 'M5 rendering smoke' { php artisan test tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php }
Run 'M5 support UI contract tests' { php artisan test tests\Feature\AdminUi\AdminSupportManagementUiTest.php }
Run 'Support backend regression' { php artisan test tests\Feature\Api\Admin\V1\AdminPrivacyComplianceSupportTest.php }
Run 'M4 rendering regression' { php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php }
Run 'M4 moderation UI regression' { php artisan test tests\Feature\AdminUi\AdminModerationReportsUiTest.php }

$optionalPreviousTests = @(
    'tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
    'tests\Feature\AdminUi\AdminUiFoundationTest.php'
)
foreach ($testFile in $optionalPreviousTests) {
    if (Test-Path $testFile) {
        Run ("Previous UI regression: " + [IO.Path]::GetFileName($testFile)) { php artisan test $testFile }
    }
}

if ($FullRegression) {
    Run 'Full Laravel regression' { php artisan test }
}

Run 'Final M5 static UI contract' { node .\setup\admin-ui-m5\verify-support-ui-contract.mjs $root }

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 5 Support Management verification passed.' -ForegroundColor Green

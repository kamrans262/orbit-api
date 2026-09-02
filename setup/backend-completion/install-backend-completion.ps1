$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

$apiRoutes = Join-Path $projectRoot 'routes\api.php'
foreach ($path in @($apiRoutes)) {
    if (-not (Test-Path $path)) { throw "Required Orbit file not found: $path" }
    $backup = "$path.pre-backend-completion-m9-backup"
    if (-not (Test-Path $backup)) { Copy-Item $path $backup -Force }
}

function Add-OrbitRequireLine {
    param([Parameter(Mandatory = $true)][string]$Path,[Parameter(Mandatory = $true)][string]$Line)
    $content = [System.IO.File]::ReadAllText($Path)
    if (-not $content.Contains($Line)) {
        if (-not $content.EndsWith("`r`n") -and -not $content.EndsWith("`n")) { $content += "`r`n" }
        $content += $Line + "`r`n"
        [System.IO.File]::WriteAllText($Path, $content, [System.Text.UTF8Encoding]::new($false))
    }
}

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/backend_completion.php';"
Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/admin_backend_completion.php';"

Push-Location $projectRoot
try { php artisan optimize:clear } finally { Pop-Location }

Write-Host ''
Write-Host 'Orbit Backend Completion & Security Audit Milestone 9 wiring installed.'
Write-Host 'Next run one command at a time:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan orbit:admin:sync-rbac'
Write-Host '  php artisan orbit:operations:sync-integrations'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php'
Write-Host '  php artisan test tests\Feature\Api\V1\Dashboard\DashboardCompletionTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan orbit:release:audit'

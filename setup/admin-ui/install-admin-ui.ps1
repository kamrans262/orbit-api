$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this installer from the Orbit Laravel project root.'
}

$webRoutes = Join-Path $projectRoot 'routes\web.php'
$adminRoutes = Join-Path $projectRoot 'routes\admin_ui.php'
$adminCss = Join-Path $projectRoot 'public\admin-ui\css\admin.css'
$adminJs = Join-Path $projectRoot 'public\admin-ui\js\pages\dashboard.js'

foreach ($required in @($webRoutes, $adminRoutes, $adminCss, $adminJs)) {
    if (-not (Test-Path $required)) {
        throw "Required Admin UI file not found: $required"
    }
}

$backup = "$webRoutes.pre-admin-ui-m10-backup"
if (-not (Test-Path $backup)) {
    Copy-Item $webRoutes $backup -Force
}

$line = "require __DIR__.'/admin_ui.php';"
$content = [System.IO.File]::ReadAllText($webRoutes)
if (-not $content.Contains($line)) {
    if (-not $content.EndsWith("`r`n") -and -not $content.EndsWith("`n")) {
        $content += "`r`n"
    }
    $content += "`r`n$line`r`n"
    [System.IO.File]::WriteAllText($webRoutes, $content, [System.Text.UTF8Encoding]::new($false))
}

Push-Location $projectRoot
try {
    php artisan optimize:clear
}
finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 1 installed.'
Write-Host 'No npm install or frontend framework is required.'
Write-Host ''
Write-Host 'Run:'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\AdminUi\AdminUiFoundationTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=admin'
Write-Host ''
Write-Host 'Then open /admin/login in your browser.'

$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this verifier from the Orbit Laravel project root.'
}

$required = @(
    'routes\admin_ui.php',
    'resources\views\admin\auth\login.blade.php',
    'resources\views\admin\auth\mfa.blade.php',
    'resources\views\admin\dashboard.blade.php',
    'resources\views\admin\layouts\app.blade.php',
    'public\admin-ui\css\admin.css',
    'public\admin-ui\js\pages\login.js',
    'public\admin-ui\js\pages\mfa.js',
    'public\admin-ui\js\pages\dashboard.js'
)

foreach ($relative in $required) {
    $path = Join-Path $projectRoot $relative
    if (-not (Test-Path $path)) {
        throw "Missing Admin UI file: $relative"
    }
}

php artisan route:list --path=admin
if ($LASTEXITCODE -ne 0) { throw 'Admin UI route verification failed.' }

Write-Host 'Orbit Admin UI Milestone 1 verification passed.'

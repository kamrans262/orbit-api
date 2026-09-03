$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\artisan')) {
    throw 'Run this installer from the Orbit Laravel project root.'
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $PSScriptRoot "backups\$stamp"
New-Item -ItemType Directory -Force -Path $backup | Out-Null

$projectRoot = (Get-Location).Path
$payloadRoot = Join-Path $PSScriptRoot 'payload'
$bootstrapFile = Join-Path $projectRoot 'bootstrap\app.php'
$middlewareTarget = Join-Path $projectRoot 'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php'
$publicTarget = Join-Path $projectRoot 'public\orbit-admin-m2-foundation-bridge.js'
$middlewareSource = Join-Path $payloadRoot 'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php'
$publicSource = Join-Path $payloadRoot 'public\orbit-admin-m2-foundation-bridge.js'

foreach ($required in @($bootstrapFile, $middlewareSource, $publicSource)) {
    if (-not (Test-Path $required)) {
        throw "Required file was not found: $required"
    }
}

foreach ($target in @($bootstrapFile, $middlewareTarget, $publicTarget)) {
    if (Test-Path $target) {
        $relative = $target.Substring($projectRoot.Length).TrimStart('\','/')
        $backupTarget = Join-Path $backup $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $backupTarget) | Out-Null
        Copy-Item $target $backupTarget -Force
    }
}

New-Item -ItemType Directory -Force -Path (Split-Path $middlewareTarget) | Out-Null
Copy-Item $middlewareSource $middlewareTarget -Force
Copy-Item $publicSource $publicTarget -Force

$bootstrap = Get-Content $bootstrapFile -Raw
$registration = '$middleware->append(\App\Http\Middleware\InjectAdminUiM2FoundationBridge::class);'

if (-not $bootstrap.Contains($registration)) {
    $pattern = '(?s)(->withMiddleware\s*\(\s*function\s*\(\s*Middleware\s+\$middleware\s*\)\s*:\s*void\s*\{)'
    if ($bootstrap -notmatch $pattern) {
        throw 'Could not safely locate the withMiddleware closure in bootstrap/app.php. Restore from the backup folder if needed.'
    }

    $bootstrap = [regex]::Replace(
        $bootstrap,
        $pattern,
        { param($m) $m.Groups[1].Value + "`r`n        " + $registration },
        1
    )

    [System.IO.File]::WriteAllText($bootstrapFile, $bootstrap, [System.Text.UTF8Encoding]::new($false))
}

php artisan optimize:clear

Write-Host ''
Write-Host 'Orbit Admin UI M2 Foundation bridge installed.' -ForegroundColor Green
Write-Host "Backup: $backup"
Write-Host ''
Write-Host 'Fixed at the HTML boundary (independent of the Foundation Vite entrypoint):'
Write-Host '  - Users sidebar -> /admin/operations/users'
Write-Host '  - Circles sidebar -> /admin/operations/circles'
Write-Host '  - M1 placeholder click handler is intercepted before it can show its toast'
Write-Host '  - Active administrator Bearer token is handed to M2 through same-tab sessionStorage'
Write-Host ''
Write-Host 'Now run:'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  npm run build'
Write-Host '  php artisan optimize:clear'

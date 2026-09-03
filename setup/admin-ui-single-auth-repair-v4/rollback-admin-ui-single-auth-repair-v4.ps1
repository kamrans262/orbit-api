[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this rollback from the Orbit Laravel project root.'
}
if (-not (Test-Path $BackupPath)) {
    throw "Backup path not found: $BackupPath"
}

$clientBackup = Join-Path $BackupPath 'admin-api-client.js'
$contractBackup = Join-Path $BackupPath 'admin-auth.generated.js'
foreach ($required in @($clientBackup, $contractBackup)) {
    if (-not (Test-Path $required)) { throw "Backup file missing: $required" }
}

Copy-Item $clientBackup (Join-Path $projectRoot 'resources\js\admin-console\admin-api-client.js') -Force
Copy-Item $contractBackup (Join-Path $projectRoot 'resources\js\admin-console\admin-auth.generated.js') -Force

& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'php artisan optimize:clear failed after rollback.' }

if (Test-Path (Join-Path $projectRoot 'package.json')) {
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build failed after rollback.' }
}

Write-Host "Rollback completed from: $BackupPath" -ForegroundColor Green

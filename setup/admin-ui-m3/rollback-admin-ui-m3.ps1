param(
    [string]$BackupPath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
$setupRoot = $PSScriptRoot

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this rollback from the Orbit Laravel project root (the directory containing artisan).'
}

if ([string]::IsNullOrWhiteSpace($BackupPath)) {
    $lastBackup = Join-Path $setupRoot '.last-backup.txt'
    if (Test-Path -LiteralPath $lastBackup) { $BackupPath = ([System.IO.File]::ReadAllText($lastBackup)).Trim() }
}

if ([string]::IsNullOrWhiteSpace($BackupPath) -or -not (Test-Path -LiteralPath $BackupPath)) {
    $root = Join-Path $projectRoot 'storage\app\orbit-admin-ui-m3-backups'
    $latest = if (Test-Path -LiteralPath $root) { Get-ChildItem -LiteralPath $root -Directory -Force | Sort-Object LastWriteTime -Descending | Select-Object -First 1 } else { $null }
    if ($null -eq $latest) { throw 'No Milestone 3 UI backup was found. Supply -BackupPath explicitly.' }
    $BackupPath = $latest.FullName
}

$backupFiles = Join-Path $BackupPath 'files'
if (-not (Test-Path -LiteralPath $backupFiles)) { throw "Invalid M3 backup: $BackupPath" }

$installedOnly = @(
    'resources\js\admin-console\sos.js',
    'resources\js\admin-console\sos-contract.js',
    'resources\views\admin\operations\sos',
    'tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php'
)
foreach ($relativePath in $installedOnly) {
    $target = Join-Path $projectRoot $relativePath
    if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
}

Get-ChildItem -LiteralPath $backupFiles -File -Recurse -Force | ForEach-Object {
    $relativePath = $_.FullName.Substring($backupFiles.Length) -replace '^[\\/]+', ''
    $destination = Join-Path $projectRoot $relativePath
    New-Item -ItemType Directory -Path (Split-Path -Path $destination -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
}

& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'M3 files were restored, but Laravel cache clearing failed.' }

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 3 rollback restored the canonical pre-M3 checkpoint.'
Write-Host "Restored backup: $BackupPath"
Write-Host 'No database tables, migrations, records, or backend services were changed.'

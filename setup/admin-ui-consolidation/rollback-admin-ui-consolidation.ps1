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
    $lastBackupFile = Join-Path $setupRoot '.last-backup.txt'
    if (Test-Path -LiteralPath $lastBackupFile) {
        $BackupPath = ([System.IO.File]::ReadAllText($lastBackupFile)).Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($BackupPath) -or -not (Test-Path -LiteralPath $BackupPath)) {
    $candidateBackupRoots = @(
        (Join-Path $projectRoot 'storage\app\orbit-admin-ui-consolidation-backups'),
        (Join-Path $setupRoot 'backups')
    )

    $latest = $candidateBackupRoots |
        Where-Object { Test-Path -LiteralPath $_ } |
        ForEach-Object { Get-ChildItem -LiteralPath $_ -Directory -Force } |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1

    if ($null -eq $latest) {
        throw 'No admin UI consolidation backup was found. Supply -BackupPath explicitly.'
    }

    $BackupPath = $latest.FullName
}

$backupFiles = Join-Path $BackupPath 'files'
if (-not (Test-Path -LiteralPath $backupFiles)) {
    throw "Invalid backup; files directory not found: $backupFiles"
}

$installedPaths = @(
    'resources\css\admin-console.css',
    'resources\js\admin-console',
    'resources\views\admin\layouts\app.blade.php',
    'resources\views\admin\partials\sidebar.blade.php',
    'resources\views\admin\partials\topbar.blade.php',
    'resources\views\admin\dashboard.blade.php',
    'resources\views\admin\operations\users\index.blade.php',
    'resources\views\admin\operations\users\show.blade.php',
    'resources\views\admin\operations\circles\index.blade.php',
    'resources\views\admin\operations\circles\show.blade.php',
    'routes\admin_console.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php'
)

foreach ($relativePath in $installedPaths) {
    $target = Join-Path $projectRoot $relativePath
    if (Test-Path -LiteralPath $target) {
        Remove-Item -LiteralPath $target -Recurse -Force
    }
}

# Restore every file that existed before installation. This includes the root
# route/build files and, when present, the historical M1/M2 files removed by the
# consolidation installer.
Get-ChildItem -LiteralPath $backupFiles -File -Recurse -Force | ForEach-Object {
    $relativePath = $_.FullName.Substring($backupFiles.Length) -replace '^[\\/]+', ''
    $destination = Join-Path $projectRoot $relativePath
    $parent = Split-Path -Path $destination -Parent
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
    Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
}

& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    throw 'Files were restored, but Laravel cache clearing failed. Run php artisan optimize:clear manually.'
}

Write-Host ''
Write-Host 'Orbit Admin UI consolidation rollback restored the pre-install checkpoint.'
Write-Host "Restored backup: $BackupPath"
Write-Host 'No database tables or migrations were changed.'

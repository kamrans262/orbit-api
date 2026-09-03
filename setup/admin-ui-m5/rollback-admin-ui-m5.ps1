param(
    [string]$BackupPath = ''
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

if (-not $BackupPath) {
    $base = Join-Path $root 'storage\app\orbit-admin-ui-m5-backups'
    if (-not (Test-Path $base)) { throw 'No M5 backups were found.' }
    $latest = Get-ChildItem $base -Directory | Sort-Object Name -Descending | Select-Object -First 1
    if (-not $latest) { throw 'No M5 backups were found.' }
    $BackupPath = $latest.FullName
}

$BackupPath = (Resolve-Path $BackupPath).Path
$manifestFile = Join-Path $BackupPath 'manifest.json'
if (-not (Test-Path $manifestFile)) { throw "M5 backup manifest is missing: $manifestFile" }
$manifest = @(Get-Content $manifestFile -Raw | ConvertFrom-Json)

foreach ($item in $manifest) {
    $relative = [string]$item.path
    $target = Join-Path $root $relative
    if ([bool]$item.existed) {
        $saved = Join-Path $BackupPath $relative
        if (-not (Test-Path $saved)) { throw "Backup file is missing: $saved" }
        New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
        Copy-Item $saved $target -Force
    } elseif (Test-Path $target) {
        Remove-Item $target -Force
    }
}

php artisan route:clear | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Laravel route cache clear failed after rollback.' }
php artisan view:clear | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Laravel view cache clear failed after rollback.' }

if (Test-Path 'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php') {
    php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'M4 rendering smoke failed after M5 rollback.' }
}

Write-Host ''
Write-Host "Orbit M5 rolled back from checkpoint: $BackupPath" -ForegroundColor Green

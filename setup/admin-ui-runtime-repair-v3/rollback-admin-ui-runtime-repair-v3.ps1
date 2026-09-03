param(
    [Parameter(Mandatory = $true)][string]$BackupPath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
node (Join-Path $PSScriptRoot 'rollback-runtime-repair-v3.mjs') $BackupPath
if ($LASTEXITCODE -ne 0) { throw "Rollback failed with exit code $LASTEXITCODE" }
php artisan optimize:clear

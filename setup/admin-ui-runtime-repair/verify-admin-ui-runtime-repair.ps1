param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

$args = @()
if ($FullRegression) { $args += '--full' }

node (Join-Path $PSScriptRoot 'verify-runtime-repair.mjs') @args
if ($LASTEXITCODE -ne 0) {
    throw "Orbit M4/M5 runtime integration verification failed with exit code $LASTEXITCODE"
}

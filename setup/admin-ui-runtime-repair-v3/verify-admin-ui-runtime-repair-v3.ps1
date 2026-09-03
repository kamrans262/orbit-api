param(
    [switch]$FullRegression
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

$args = @((Join-Path $PSScriptRoot 'verify-runtime-repair-v3.mjs'))
if ($FullRegression) { $args += '--full' }
node @args
if ($LASTEXITCODE -ne 0) {
    throw "Orbit M4/M5 canonical-auth verification failed with exit code $LASTEXITCODE"
}

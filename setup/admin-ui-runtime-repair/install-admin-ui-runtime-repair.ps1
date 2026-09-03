$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

node (Join-Path $PSScriptRoot 'install-runtime-repair.mjs')
if ($LASTEXITCODE -ne 0) {
    throw "Orbit M4/M5 runtime integration repair failed with exit code $LASTEXITCODE"
}

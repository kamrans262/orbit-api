param(
    [Parameter(Mandatory = $true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
$generator = Join-Path $PSScriptRoot 'generate-sos-contract.mjs'

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this script from the Orbit Laravel project root (the directory containing artisan).'
}
if (-not (Test-Path -LiteralPath $generator)) {
    throw "Admin SOS route-contract generator is missing: $generator"
}

$routeOutput = & php artisan route:list --json --path=admin/v1/sos 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "Could not inspect the Admin SOS route inventory.`n$($routeOutput -join "`n")"
}

$routeOutput | & node $generator $OutputPath $projectRoot
if ($LASTEXITCODE -ne 0) {
    throw 'Could not generate the Admin SOS browser route contract from Laravel route metadata.'
}

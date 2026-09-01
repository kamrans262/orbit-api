$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).Path
$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$consoleRoutes = Join-Path $projectRoot 'routes\console.php'
$channelRoutes = Join-Path $projectRoot 'routes\channels.php'

foreach ($requiredFile in @($apiRoutes, $consoleRoutes, $channelRoutes)) {
    if (-not (Test-Path $requiredFile)) {
        throw "Required Orbit file not found: $requiredFile"
    }
}

function Add-OrbitRequireLine {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Line
    )

    $exists = Select-String -Path $Path -Pattern $Line -SimpleMatch -Quiet
    if (-not $exists) {
        Add-Content -Path $Path -Value "`r`n$Line"
    }
}

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/sos.php';"
Add-OrbitRequireLine -Path $consoleRoutes -Line "require __DIR__.'/console_sos.php';"
Add-OrbitRequireLine -Path $channelRoutes -Line "require __DIR__.'/channels_sos.php';"

php artisan optimize:clear

Write-Host ''
Write-Host 'Orbit SOS routes, private channels, and scheduler hooks installed.'
Write-Host 'Next run:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\V1\Sos\SosTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=sos'
Write-Host '  php artisan event:list'
Write-Host '  php artisan schedule:list'

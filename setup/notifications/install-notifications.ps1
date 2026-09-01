$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).Path
$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$consoleRoutes = Join-Path $projectRoot 'routes\console.php'
$channelRoutes = Join-Path $projectRoot 'routes\channels.php'
$providersFile = Join-Path $projectRoot 'bootstrap\providers.php'

foreach ($requiredFile in @($apiRoutes, $consoleRoutes, $channelRoutes, $providersFile)) {
    if (-not (Test-Path $requiredFile)) { throw "Required Orbit file not found: $requiredFile" }
}

function Add-OrbitRequireLine {
    param([Parameter(Mandatory = $true)][string]$Path, [Parameter(Mandatory = $true)][string]$Line)
    if (-not (Select-String -Path $Path -Pattern $Line -SimpleMatch -Quiet)) { Add-Content -Path $Path -Value "`r`n$Line" }
}

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/notifications.php';"
Add-OrbitRequireLine -Path $consoleRoutes -Line "require __DIR__.'/console_notifications.php';"
Add-OrbitRequireLine -Path $channelRoutes -Line "require __DIR__.'/channels_notifications.php';"

$providerClass = 'App\Providers\NotificationsServiceProvider::class'
$providersContent = Get-Content -Path $providersFile -Raw
if (-not $providersContent.Contains($providerClass)) {
    $closingIndex = $providersContent.LastIndexOf('];')
    if ($closingIndex -lt 0) { throw "Could not safely update $providersFile because its provider array closing token was not found." }
    $providersContent = $providersContent.Insert($closingIndex, "    $providerClass,`r`n")
    [System.IO.File]::WriteAllText($providersFile, $providersContent, [System.Text.UTF8Encoding]::new($false))
}

php artisan optimize:clear
Write-Host ''
Write-Host 'Orbit Notifications routes, provider, private user channel, SOS recovery import, and schedules installed.'
Write-Host 'Next run:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=notifications'
Write-Host '  php artisan event:list'
Write-Host '  php artisan schedule:list'

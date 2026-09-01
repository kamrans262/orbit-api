$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).Path
$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$providersFile = Join-Path $projectRoot 'bootstrap\providers.php'

foreach ($requiredFile in @($apiRoutes, $providersFile)) {
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

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/activity.php';"

$providerClass = 'App\Providers\ActivityServiceProvider::class'
$providersContent = Get-Content -Path $providersFile -Raw

if (-not $providersContent.Contains($providerClass)) {
    $closingIndex = $providersContent.LastIndexOf('];')

    if ($closingIndex -lt 0) {
        throw "Could not safely update $providersFile because its provider array closing token was not found."
    }

    $providerLine = "    $providerClass,`r`n"
    $providersContent = $providersContent.Insert($closingIndex, $providerLine)
    [System.IO.File]::WriteAllText(
        $providersFile,
        $providersContent,
        [System.Text.UTF8Encoding]::new($false)
    )
}

php artisan optimize:clear

Write-Host ''
Write-Host 'Orbit Activity routes, provider, cross-domain listeners, and membership tracking installed.'
Write-Host 'Next run:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\V1\Activity\ActivityTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=activity'
Write-Host '  php artisan event:list'

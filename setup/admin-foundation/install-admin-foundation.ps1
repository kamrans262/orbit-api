$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).ProviderPath

if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$consoleRoutes = Join-Path $projectRoot 'routes\console.php'
$providersFile = Join-Path $projectRoot 'bootstrap\providers.php'

foreach ($path in @($apiRoutes, $consoleRoutes, $providersFile)) {
    if (-not (Test-Path $path)) {
        throw "Required Orbit file not found: $path"
    }

    $backup = "$path.pre-admin-foundation-backup"
    if (-not (Test-Path $backup)) {
        Copy-Item $path $backup -Force
    }
}

function Add-OrbitRequireLine {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Line
    )

    if (-not (Select-String -Path $Path -Pattern $Line -SimpleMatch -Quiet)) {
        Add-Content -Path $Path -Value "`r`n$Line"
    }
}

Add-OrbitRequireLine -Path $apiRoutes -Line "require __DIR__.'/admin.php';"
Add-OrbitRequireLine -Path $consoleRoutes -Line "require __DIR__.'/console_admin.php';"

$providerClass = 'App\Providers\AdminServiceProvider::class'
$providersContent = Get-Content -Path $providersFile -Raw

if (-not $providersContent.Contains($providerClass)) {
    $closingIndex = $providersContent.LastIndexOf('];')

    if ($closingIndex -lt 0) {
        throw "Could not safely update $providersFile because its provider array closing token was not found."
    }

    $providersContent = $providersContent.Insert($closingIndex, "    $providerClass,`r`n")

    [System.IO.File]::WriteAllText(
        $providersFile,
        $providersContent,
        [System.Text.UTF8Encoding]::new($false)
    )
}

Push-Location $projectRoot
try {
    php artisan optimize:clear
}
finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Orbit Admin Platform Foundation wiring installed.'
Write-Host 'Next run one command at a time:'
Write-Host '  php artisan migrate'
Write-Host '  php artisan orbit:admin:sync-rbac'
Write-Host '  php artisan optimize:clear'
Write-Host '  vendor\bin\pint'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php'
Write-Host '  php artisan test'
Write-Host '  php artisan route:list --path=admin/v1'
Write-Host '  php artisan schedule:list'

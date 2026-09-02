$ErrorActionPreference = 'Stop'
$projectRoot = (Get-Location).ProviderPath
if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) { throw 'Run this script from C:\laravel-projects\orbit_api' }
$apiRoutes = Join-Path $projectRoot 'routes\api.php'
$consoleRoutes = Join-Path $projectRoot 'routes\console.php'
$channelRoutes = Join-Path $projectRoot 'routes\channels.php'
function Add-OrbitRequireLine { param([string]$Path,[string]$Line) $content=[System.IO.File]::ReadAllText($Path); if(-not $content.Contains($Line)){if(-not $content.EndsWith("`r`n") -and -not $content.EndsWith("`n")){$content+="`r`n"};$content+=$Line+"`r`n";[System.IO.File]::WriteAllText($Path,$content,[System.Text.UTF8Encoding]::new($false))}}
foreach($path in @($apiRoutes,$consoleRoutes,$channelRoutes)){if(-not(Test-Path $path)){throw "Required Orbit file not found: $path"};$backup="$path.pre-admin-analytics-operations-m8-backup";if(-not(Test-Path $backup)){Copy-Item $path $backup -Force}}
Add-OrbitRequireLine $apiRoutes "require __DIR__.'/analytics_operations.php';"
Add-OrbitRequireLine $apiRoutes "require __DIR__.'/admin_analytics_operations.php';"
Add-OrbitRequireLine $consoleRoutes "require __DIR__.'/console_analytics_operations.php';"
Add-OrbitRequireLine $channelRoutes "require __DIR__.'/channels_admin_operations.php';"
php artisan optimize:clear
Write-Host 'Orbit Admin Analytics / Configuration / System Operations Milestone 8 wiring installed.'

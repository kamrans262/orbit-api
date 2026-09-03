$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\artisan')) { throw 'Run this installer from the Orbit Laravel project root.' }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = ".\setup\admin-ui-m2\backups\$stamp"
New-Item -ItemType Directory -Force -Path "$backup\resources\css", "$backup\resources\js", "$backup\routes" | Out-Null

foreach ($file in @('.\resources\css\app.css', '.\resources\js\app.js', '.\routes\web.php')) {
    if (Test-Path $file) {
        $relative = $file -replace '^\.[\\/]', ''
        $target = Join-Path $backup $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $target) | Out-Null
        Copy-Item $file $target -Force
    }
}

$cssImport = '@import "./admin-ui-m2.css";'
$cssFile = '.\resources\css\app.css'
$css = Get-Content $cssFile -Raw
if (-not $css.Contains($cssImport)) {
    [System.IO.File]::WriteAllText((Resolve-Path $cssFile), "$cssImport`r`n$css", [System.Text.UTF8Encoding]::new($false))
}

$jsImport = "import './admin-ui-m2/index.js';"
$jsFile = '.\resources\js\app.js'
$js = Get-Content $jsFile -Raw
if (-not $js.Contains($jsImport)) {
    [System.IO.File]::WriteAllText((Resolve-Path $jsFile), "$js`r`n$jsImport`r`n", [System.Text.UTF8Encoding]::new($false))
}

$routeImport = "require __DIR__.'/admin_ui_m2.php';"
$routeFile = '.\routes\web.php'
$routes = Get-Content $routeFile -Raw
if (-not $routes.Contains($routeImport)) {
    [System.IO.File]::WriteAllText((Resolve-Path $routeFile), "$routes`r`n$routeImport`r`n", [System.Text.UTF8Encoding]::new($false))
}

php artisan optimize:clear
Write-Host ''
Write-Host 'Orbit Admin UI Milestone 2 installed.'
Write-Host "Backup of modified entry files: $backup"
Write-Host 'Next run one command at a time:'
Write-Host '  vendor\bin\pint --test'
Write-Host '  php artisan test tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php'
Write-Host '  npm run build'
Write-Host '  php artisan route:list --path=admin/operations'
Write-Host ''
Write-Host 'Open: /admin/operations/users and /admin/operations/circles'

$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\artisan')) {
    throw 'Run this installer from the Orbit Laravel project root.'
}

$payloadRoot = Join-Path $PSScriptRoot 'payload'
if (-not (Test-Path $payloadRoot)) {
    throw "Hotfix payload was not found at $payloadRoot"
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $PSScriptRoot "backups\$stamp"
New-Item -ItemType Directory -Force -Path $backup | Out-Null

$relativeFiles = @(
    'resources\css\admin-ui-m2.css',
    'resources\js\admin-ui-m2\api.js',
    'resources\js\admin-ui-m2\index.js',
    'resources\js\admin-ui-m2\foundation-integration.js',
    'resources\views\admin\operations\layouts\shell.blade.php'
)

foreach ($relative in $relativeFiles) {
    $target = Join-Path (Get-Location).Path $relative
    if (Test-Path $target) {
        $backupTarget = Join-Path $backup $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $backupTarget) | Out-Null
        Copy-Item $target $backupTarget -Force
    }
}

foreach ($relative in $relativeFiles) {
    $source = Join-Path $payloadRoot $relative
    $target = Join-Path (Get-Location).Path $relative
    if (-not (Test-Path $source)) {
        throw "Required hotfix file was not found: $source"
    }
    New-Item -ItemType Directory -Force -Path (Split-Path $target) | Out-Null
    Copy-Item $source $target -Force
}

$cssFile = '.\resources\css\app.css'
$cssImport = '@import "./admin-ui-m2.css";'
if (-not (Test-Path $cssFile)) { throw 'resources/css/app.css was not found.' }
$css = Get-Content $cssFile -Raw
if (-not $css.Contains($cssImport)) {
    [System.IO.File]::WriteAllText((Resolve-Path $cssFile), "$cssImport`r`n$css", [System.Text.UTF8Encoding]::new($false))
}

$jsFile = '.\resources\js\app.js'
$jsImport = "import './admin-ui-m2/index.js';"
if (-not (Test-Path $jsFile)) { throw 'resources/js/app.js was not found.' }
$js = Get-Content $jsFile -Raw
if (-not $js.Contains($jsImport)) {
    [System.IO.File]::WriteAllText((Resolve-Path $jsFile), "$js`r`n$jsImport`r`n", [System.Text.UTF8Encoding]::new($false))
}

php artisan optimize:clear

Write-Host ''
Write-Host 'Orbit Admin UI M2 unification hotfix installed.' -ForegroundColor Green
Write-Host "Backup of replaced M2 files: $backup"
Write-Host ''
Write-Host 'Fixed:'
Write-Host '  - Foundation Users/Circles sidebar navigation'
Write-Host '  - M2 shell visual mismatch'
Write-Host '  - M1/M2 administrator token handoff'
Write-Host ''
Write-Host 'Now run one command at a time:'
Write-Host '  vendor\bin\pint --test'
Write-Host '  npm run build'
Write-Host '  php artisan test tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
Write-Host '  php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php'

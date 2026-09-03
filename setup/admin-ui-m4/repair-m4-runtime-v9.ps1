$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Read-Utf8([string]$Path) {
    $resolved = (Resolve-Path $Path).Path
    return [System.IO.File]::ReadAllText($resolved, [System.Text.Encoding]::UTF8)
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $parent = Split-Path $Path -Parent
    if ($parent -and -not (Test-Path $parent)) {
        New-Item -ItemType Directory -Force -Path $parent | Out-Null
    }
    [System.IO.File]::WriteAllText($Path, $Content, [System.Text.UTF8Encoding]::new($false))
}

function Copy-Tree([string]$Source, [string]$Destination) {
    if (-not (Test-Path $Source)) { return }
    New-Item -ItemType Directory -Force -Path $Destination | Out-Null
    Get-ChildItem $Source -Recurse -File | ForEach-Object {
        $sourceRoot = (Resolve-Path $Source).Path.TrimEnd('\','/')
        $relative = $_.FullName.Substring($sourceRoot.Length).TrimStart('\','/')
        $target = Join-Path $Destination $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
        Copy-Item $_.FullName $target -Force
    }
}

$required = @(
    'routes\admin_ui_m4.php',
    'resources\js\admin-console\moderation-m4.js',
    'resources\css\admin-console-m4.css',
    'tests\Feature\AdminUi\AdminModerationReportsUiTest.php'
)
foreach ($rel in $required) {
    if (-not (Test-Path $rel)) { throw "M4 prerequisite missing: $rel" }
}

$routeJson = php artisan route:list --json
if ($LASTEXITCODE -ne 0) { throw 'Unable to inspect Laravel routes before M4 runtime recovery.' }
$routes = $routeJson | ConvertFrom-Json
$requiredRouteNames = @(
    'admin.console.operations.moderation.index',
    'admin.console.operations.moderation.reports.show',
    'admin.console.operations.moderation.appeals.index',
    'admin.console.operations.moderation.appeals.show',
    'admin.console.operations.moderation.risk.index',
    'admin.console.operations.moderation.risk.show'
)
foreach ($routeName in $requiredRouteNames) {
    if (-not ($routes | Where-Object { $_.name -eq $routeName } | Select-Object -First 1)) {
        throw "Required M4 web route is missing: $routeName"
    }
}

$sosView = Get-ChildItem 'resources\views' -Recurse -Filter '*.blade.php' | Where-Object {
    $source = Read-Utf8 $_.FullName
    $source -match 'data-orbit-view="sos-(index|show)"'
} | Select-Object -First 1
if (-not $sosView) { throw 'Could not locate a working canonical M3 SOS view.' }

$sosSource = Read-Utf8 $sosView.FullName
$layoutMatch = [regex]::Match($sosSource, '@extends\([''"]([^''"]+)[''"]\)')
$sectionMatch = [regex]::Match($sosSource, '@section\([''"]([^''"]+)[''"]\)')
if (-not $layoutMatch.Success -or -not $sectionMatch.Success) {
    throw 'Could not derive the canonical layout and section from M3 SOS.'
}
$layout = $layoutMatch.Groups[1].Value
$section = $sectionMatch.Groups[1].Value
if ($layout -match '__ORBIT_' -or $section -match '__ORBIT_') {
    throw 'Canonical M3 SOS still contains an installer placeholder; refusing recovery.'
}

$templateRoot = Join-Path $PSScriptRoot 'recovery-v9\templates'
if (-not (Test-Path $templateRoot)) { throw 'M4 v9 recovery templates are missing.' }

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path $root ("storage\app\orbit-admin-ui-m4-runtime-staging\$timestamp")
$backup = Join-Path $root ("storage\app\orbit-admin-ui-m4-runtime-backups\$timestamp")
$liveRoot = Join-Path $root 'resources\views\admin\console\operations\moderation'
New-Item -ItemType Directory -Force -Path $staging | Out-Null
New-Item -ItemType Directory -Force -Path $backup | Out-Null

$templates = @(
    @{ Template = 'index.blade.php.tpl'; Live = 'index.blade.php' },
    @{ Template = 'reports\show.blade.php.tpl'; Live = 'reports\show.blade.php' },
    @{ Template = 'appeals\index.blade.php.tpl'; Live = 'appeals\index.blade.php' },
    @{ Template = 'appeals\show.blade.php.tpl'; Live = 'appeals\show.blade.php' },
    @{ Template = 'risk\index.blade.php.tpl'; Live = 'risk\index.blade.php' },
    @{ Template = 'risk\show.blade.php.tpl'; Live = 'risk\show.blade.php' }
)

foreach ($item in $templates) {
    $templatePath = Join-Path $templateRoot $item.Template
    if (-not (Test-Path $templatePath)) { throw "Recovery template missing: $($item.Template)" }
    $rendered = (Read-Utf8 $templatePath).Replace('__ORBIT_LAYOUT__', $layout).Replace('__ORBIT_SECTION__', $section)
    if ($rendered -match '__ORBIT_[A-Z0-9_]+__') { throw "Unresolved recovery placeholder in $($item.Template)" }
    $expectedSingle = "@extends('$layout')"
    $expectedDouble = '@extends("' + $layout + '")'
    if (-not $rendered.Contains($expectedSingle) -and -not $rendered.Contains($expectedDouble)) {
        throw "Rendered view has the wrong layout: $($item.Template)"
    }
    $target = Join-Path $staging $item.Live
    Write-Utf8NoBom $target $rendered
}

$stagedFiles = Get-ChildItem $staging -Recurse -Filter '*.blade.php'
if ($stagedFiles.Count -ne 6) { throw "Expected 6 staged M4 views, found $($stagedFiles.Count)." }
foreach ($file in $stagedFiles) {
    if ((Read-Utf8 $file.FullName) -match '__ORBIT_[A-Z0-9_]+__') { throw "Placeholder remains in staged view: $($file.FullName)" }
}

if (Test-Path $liveRoot) {
    Copy-Tree $liveRoot (Join-Path $backup 'resources\views\admin\console\operations\moderation')
}
$smokeTest = Join-Path $root 'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php'
if (Test-Path $smokeTest) {
    $testBackup = Join-Path $backup 'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php'
    New-Item -ItemType Directory -Force -Path (Split-Path $testBackup -Parent) | Out-Null
    Copy-Item $smokeTest $testBackup -Force
}

try {
    foreach ($item in $templates) {
        $source = Join-Path $staging $item.Live
        $destination = Join-Path $liveRoot $item.Live
        Write-Utf8NoBom $destination (Read-Utf8 $source)
    }

    $testTemplate = Join-Path $PSScriptRoot 'recovery-v9\AdminModerationRenderingSmokeTest.php.tpl'
    if (-not (Test-Path $testTemplate)) { throw 'M4 rendering smoke-test template is missing.' }
    Write-Utf8NoBom $smokeTest (Read-Utf8 $testTemplate)

    node '.\setup\admin-ui-m4\verify-m4-runtime-v9.mjs' | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'M4 runtime static verification failed after recovery.' }

    php artisan view:clear | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'Laravel view cache clear failed after M4 runtime recovery.' }

    php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'M4 six-route rendering smoke test failed; automatic rollback will run.' }
}
catch {
    if (Test-Path (Join-Path $backup 'resources\views\admin\console\operations\moderation')) {
        if (Test-Path $liveRoot) { Remove-Item $liveRoot -Recurse -Force }
        Copy-Tree (Join-Path $backup 'resources\views\admin\console\operations\moderation') $liveRoot
    }
    $testBackup = Join-Path $backup 'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php'
    if (Test-Path $testBackup) {
        Copy-Item $testBackup $smokeTest -Force
    } elseif (Test-Path $smokeTest) {
        Remove-Item $smokeTest -Force
    }
    php artisan view:clear | Out-Null
    throw
}

Write-Host ''
Write-Host 'Orbit M4 runtime views recovered and render-smoke tested.' -ForegroundColor Green
Write-Host "Canonical layout: $layout"
Write-Host "Canonical section: $section"
Write-Host "Backup: $backup"
Write-Host 'Next recommended command:'
Write-Host '  .\setup\admin-ui-m4\verify-m4-runtime-v9.ps1 -FullRegression'

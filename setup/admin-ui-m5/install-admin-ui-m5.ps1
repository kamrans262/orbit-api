$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root
$utf8 = [System.Text.Encoding]::UTF8
$utf8NoBom = [System.Text.UTF8Encoding]::new($false)

function Read-Utf8([string]$Path) {
    $resolved = (Resolve-Path $Path).Path
    return [System.IO.File]::ReadAllText($resolved, $utf8)
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $parent = Split-Path $Path -Parent
    if ($parent -and -not (Test-Path $parent)) { New-Item -ItemType Directory -Force -Path $parent | Out-Null }
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Assert-LastExit([string]$Message) {
    if ($LASTEXITCODE -ne 0) { throw $Message }
}

function Relative-ToRoot([string]$FullPath) {
    $prefix = $root.TrimEnd('\','/') + [IO.Path]::DirectorySeparatorChar
    if (-not $FullPath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Path is outside the project root: $FullPath"
    }
    return $FullPath.Substring($prefix.Length)
}

function Get-PreviousUiHashes([string]$SidebarRelative) {
    $hashes = [ordered]@{}
    $sidebarAbsolute = (Join-Path $root $SidebarRelative)
    $files = @()

    if (Test-Path 'resources\views\admin\console') {
        $files += Get-ChildItem 'resources\views\admin\console' -Recurse -File | Where-Object {
            $_.FullName -ne $sidebarAbsolute -and $_.FullName -notmatch '[\\/]operations[\\/]support[\\/]'
        }
    }
    if (Test-Path 'resources\js\admin-console') {
        $files += Get-ChildItem 'resources\js\admin-console' -File | Where-Object {
            $_.Name -ne 'index.js' -and $_.Name -notin @('support-m5.js','support-routes.generated.js')
        }
    }
    if (Test-Path 'resources\css') {
        $files += Get-ChildItem 'resources\css' -File | Where-Object {
            $_.Name -like 'admin-console*.css' -and $_.Name -ne 'admin-console-m5.css'
        }
    }
    if (Test-Path 'routes') {
        $files += Get-ChildItem 'routes' -File -Filter 'admin_ui_*.php' | Where-Object { $_.Name -ne 'admin_ui_m5.php' }
    }
    if (Test-Path 'tests\Feature\AdminUi') {
        $files += Get-ChildItem 'tests\Feature\AdminUi' -File -Filter '*.php' | Where-Object {
            $_.Name -notin @('AdminSupportManagementUiTest.php','AdminSupportRenderingSmokeTest.php')
        }
    }

    foreach ($file in ($files | Sort-Object FullName -Unique)) {
        $relative = Relative-ToRoot $file.FullName
        $hashes[$relative] = (Get-FileHash -Algorithm SHA256 -Path $file.FullName).Hash
    }
    return $hashes
}

function Assert-PreviousUiUnchanged($Before, [string]$SidebarRelative) {
    $after = Get-PreviousUiHashes $SidebarRelative
    foreach ($key in $Before.Keys) {
        if (-not $after.Contains($key)) { throw "Previous UI file disappeared during M5 install: $key" }
        if ($after[$key] -ne $Before[$key]) { throw "Previous UI file changed during M5 install: $key" }
    }
}

$required = @(
    'resources\js\admin-console\index.js',
    'resources\views\admin\console\operations\moderation\index.blade.php',
    'resources\js\admin-console\moderation-m4.js',
    'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php',
    'tests\Feature\AdminUi\AdminModerationReportsUiTest.php',
    'tests\Feature\Api\Admin\V1\AdminPrivacyComplianceSupportTest.php',
    'vendor\bin\pint',
    'package.json'
)
foreach ($path in $required) {
    if (-not (Test-Path $path)) { throw "M5 prerequisite is missing: $path" }
}

Write-Host 'Preflight: verifying the current M4 runtime before any M5 file is changed...' -ForegroundColor Cyan
php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host
Assert-LastExit 'Current M4 rendering smoke test is not green. M5 installation was not started.'

$placeholderFiles = @()
Get-ChildItem 'resources\views' -Recurse -Filter '*.blade.php' -File | ForEach-Object {
    if ((Read-Utf8 $_.FullName) -match '__ORBIT_[A-Z0-9_]+__') { $placeholderFiles += $_.FullName }
}
if ($placeholderFiles.Count -gt 0) {
    throw "Live Blade installer placeholders exist before M5: $($placeholderFiles -join ', ')"
}

$routeJsonLines = php artisan route:list --json
Assert-LastExit 'Unable to inspect the live Laravel route inventory.'
$routeJsonText = [string]::Join("`n", [string[]]$routeJsonLines)
$routes = $routeJsonText | ConvertFrom-Json
if (-not ($routes | Where-Object { $_.name -eq 'admin.console.operations.moderation.index' })) {
    throw 'Canonical M4 moderation web route is missing.'
}
if (-not ($routes | Where-Object { [string]$_.uri -eq 'api/admin/v1/support/tickets' -and [string]$_.method -match 'GET' })) {
    throw 'Required support ticket list backend route is missing.'
}
if (-not ($routes | Where-Object { [string]$_.uri -match '^api/admin/v1/support/tickets/\{[^}]+\}$' -and [string]$_.method -match 'GET' })) {
    throw 'Required support ticket detail backend route is missing.'
}

$sidebarCandidates = @()
Get-ChildItem 'resources\views' -Recurse -Filter '*.blade.php' -File | ForEach-Object {
    $source = Read-Utf8 $_.FullName
    if ($source -match 'orbit-nav-item' -and $source -match 'admin\.console\.operations\.moderation\.index' -and $source -match '<span(?:\s+[^>]*)?>\s*Support\s*</span>') {
        $sidebarCandidates += $_
    }
}
if ($sidebarCandidates.Count -ne 1) {
    throw "Expected exactly one canonical sidebar containing M4 and Support, found $($sidebarCandidates.Count)."
}
$sidebarFile = $sidebarCandidates[0]
$sidebarRel = Relative-ToRoot $sidebarFile.FullName

$m4Source = Read-Utf8 'resources\views\admin\console\operations\moderation\index.blade.php'
$layoutMatch = [regex]::Match($m4Source, '@extends\([''"]([^''"]+)[''"]\)')
$sectionMatch = [regex]::Match($m4Source, '@section\([''"]([^''"]+)[''"]\)')
if (-not $layoutMatch.Success -or -not $sectionMatch.Success) {
    throw 'Could not discover the canonical Blade layout and section from the working M4 view.'
}
$layout = $layoutMatch.Groups[1].Value
$section = $sectionMatch.Groups[1].Value

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path $root ('storage\app\orbit-admin-ui-m5-staging\' + $stamp)
New-Item -ItemType Directory -Force -Path $staging | Out-Null

$routeJsonFile = Join-Path $staging 'routes.json'
Write-Utf8NoBom $routeJsonFile ($routeJsonText + "`n")

$stageRoute = Join-Path $staging 'routes\admin_ui_m5.php'
$stageIndex = Join-Path $staging 'resources\views\admin\console\operations\support\index.blade.php'
$stageShow = Join-Path $staging 'resources\views\admin\console\operations\support\show.blade.php'
$stageRuntime = Join-Path $staging 'resources\js\admin-console\support-m5.js'
$stageContract = Join-Path $staging 'resources\js\admin-console\support-routes.generated.js'
$stageCss = Join-Path $staging 'resources\css\admin-console-m5.css'
$stageUiTest = Join-Path $staging 'tests\Feature\AdminUi\AdminSupportManagementUiTest.php'
$stageSmokeTest = Join-Path $staging 'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php'

Write-Utf8NoBom $stageRoute (Read-Utf8 (Join-Path $PSScriptRoot 'templates\routes_admin_ui_m5.php.tpl'))
$indexTemplate = Read-Utf8 (Join-Path $PSScriptRoot 'templates\support-index.blade.php.tpl')
$showTemplate = Read-Utf8 (Join-Path $PSScriptRoot 'templates\support-show.blade.php.tpl')
Write-Utf8NoBom $stageIndex ($indexTemplate.Replace('__ORBIT_LAYOUT__', $layout).Replace('__ORBIT_SECTION__', $section))
Write-Utf8NoBom $stageShow ($showTemplate.Replace('__ORBIT_LAYOUT__', $layout).Replace('__ORBIT_SECTION__', $section))
Write-Utf8NoBom $stageRuntime (Read-Utf8 (Join-Path $PSScriptRoot 'templates\support-m5.js'))
Write-Utf8NoBom $stageCss (Read-Utf8 (Join-Path $PSScriptRoot 'templates\admin-console-m5.css'))
Write-Utf8NoBom $stageUiTest (Read-Utf8 (Join-Path $PSScriptRoot 'templates\AdminSupportManagementUiTest.php'))
Write-Utf8NoBom $stageSmokeTest (Read-Utf8 (Join-Path $PSScriptRoot 'templates\AdminSupportRenderingSmokeTest.php'))

node (Join-Path $PSScriptRoot 'generate-support-contract.mjs') $root $routeJsonFile $stageContract | Out-Host
Assert-LastExit 'Support backend route contract generation failed.'

node --check $stageRuntime | Out-Host
Assert-LastExit 'M5 JavaScript syntax check failed.'
node --check $stageContract | Out-Host
Assert-LastExit 'Generated M5 support contract syntax check failed.'
php -l $stageRoute | Out-Host
Assert-LastExit 'M5 web route syntax check failed.'
php -l $stageUiTest | Out-Host
Assert-LastExit 'M5 UI test syntax check failed.'
php -l $stageSmokeTest | Out-Host
Assert-LastExit 'M5 rendering smoke test syntax check failed.'

$stagePlaceholders = @()
Get-ChildItem $staging -Recurse -Filter '*.blade.php' -File | ForEach-Object {
    if ((Read-Utf8 $_.FullName) -match '__ORBIT_[A-Z0-9_]+__') { $stagePlaceholders += $_.FullName }
}
if ($stagePlaceholders.Count -gt 0) {
    throw "Staging still contains installer placeholders: $($stagePlaceholders -join ', ')"
}

$web = Read-Utf8 'routes\web.php'
$web = ($web -replace "`r`n", "`n") -replace "`r", "`n"
if ($web -notmatch 'admin_ui_m5\.php') {
    $web = $web.TrimEnd() + "`n`n// ORBIT ADMIN UI M5 START`nrequire __DIR__.'/admin_ui_m5.php';`n// ORBIT ADMIN UI M5 END`n"
}

$entry = Read-Utf8 'resources\js\admin-console\index.js'
if ($entry -notmatch "support-m5\.js") {
    $entry = "import './support-m5.js';`r`n" + $entry
}

$sidebar = Read-Utf8 $sidebarFile.FullName
$newSupport = @'
<a href="{{ route('admin.console.operations.support.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.support.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.console.operations.support.*')) aria-current="page" @endif>
                <span class="orbit-nav-icon" aria-hidden="true">?</span><span>Support</span>
            </a>
'@.Trim()
$activePattern = '(?s)<a\b(?=[^>]*class=["''][^"'']*\borbit-nav-item\b[^"'']*["''])[^>]*>(?:(?!</a>).)*?<span(?:\s+[^>]*)?>\s*Support\s*</span>(?:(?!</a>).)*?</a>'
$disabledPattern = '(?s)<span\s+class=["''][^"'']*\borbit-nav-item\b[^"'']*\borbit-nav-item--disabled\b[^"'']*["''][^>]*>\s*<span\s+class=["'']orbit-nav-icon["''][^>]*>[^<]*</span>\s*<span(?:\s+[^>]*)?>\s*Support\s*</span>\s*(?:<small>.*?</small>)?\s*</span>'
$activeMatches = [regex]::Matches($sidebar, $activePattern)
$disabledMatches = [regex]::Matches($sidebar, $disabledPattern)
$totalSupportItems = $activeMatches.Count + $disabledMatches.Count
if ($totalSupportItems -ne 1) {
    throw "Expected exactly one canonical Support sidebar item, found $totalSupportItems. Refusing an unsafe rewrite."
}
if ($activeMatches.Count -eq 1) {
    $sidebar = [regex]::Replace($sidebar, $activePattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $newSupport }, 1)
} else {
    $sidebar = [regex]::Replace($sidebar, $disabledPattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $newSupport }, 1)
}

$stageWeb = Join-Path $staging 'shared\routes\web.php'
$stageEntry = Join-Path $staging 'shared\resources\js\admin-console\index.js'
$stageSidebar = Join-Path $staging ('shared\' + $sidebarRel)
Write-Utf8NoBom $stageWeb $web
Write-Utf8NoBom $stageEntry $entry
Write-Utf8NoBom $stageSidebar $sidebar

$previousHashes = Get-PreviousUiHashes $sidebarRel

$targets = @(
    'routes\web.php',
    'resources\js\admin-console\index.js',
    $sidebarRel,
    'routes\admin_ui_m5.php',
    'resources\views\admin\console\operations\support\index.blade.php',
    'resources\views\admin\console\operations\support\show.blade.php',
    'resources\js\admin-console\support-m5.js',
    'resources\js\admin-console\support-routes.generated.js',
    'resources\css\admin-console-m5.css',
    'tests\Feature\AdminUi\AdminSupportManagementUiTest.php',
    'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php'
) | Sort-Object -Unique

$backup = Join-Path $root ('storage\app\orbit-admin-ui-m5-backups\' + $stamp)
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$manifest = @()
foreach ($relative in $targets) {
    $source = Join-Path $root $relative
    $existed = Test-Path $source
    $manifest += [ordered]@{ path = $relative; existed = $existed }
    if ($existed) {
        $destination = Join-Path $backup $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $destination -Parent) | Out-Null
        Copy-Item $source $destination -Force
    }
}
Write-Utf8NoBom (Join-Path $backup 'manifest.json') (($manifest | ConvertTo-Json -Depth 4) + "`n")

function Restore-M5Backup {
    foreach ($item in $manifest) {
        $target = Join-Path $root ([string]$item['path'])
        if ([bool]$item['existed']) {
            $saved = Join-Path $backup ([string]$item['path'])
            New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
            Copy-Item $saved $target -Force
        } elseif (Test-Path $target) {
            Remove-Item $target -Force
        }
    }
    php artisan route:clear | Out-Null
    php artisan view:clear | Out-Null
}

try {
    Write-Utf8NoBom 'routes\web.php' (Read-Utf8 $stageWeb)
    Write-Utf8NoBom 'resources\js\admin-console\index.js' (Read-Utf8 $stageEntry)
    Write-Utf8NoBom $sidebarFile.FullName (Read-Utf8 $stageSidebar)

    $installMap = [ordered]@{}
    $installMap[$stageRoute] = 'routes\admin_ui_m5.php'
    $installMap[$stageIndex] = 'resources\views\admin\console\operations\support\index.blade.php'
    $installMap[$stageShow] = 'resources\views\admin\console\operations\support\show.blade.php'
    $installMap[$stageRuntime] = 'resources\js\admin-console\support-m5.js'
    $installMap[$stageContract] = 'resources\js\admin-console\support-routes.generated.js'
    $installMap[$stageCss] = 'resources\css\admin-console-m5.css'
    $installMap[$stageUiTest] = 'tests\Feature\AdminUi\AdminSupportManagementUiTest.php'
    $installMap[$stageSmokeTest] = 'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php'
    foreach ($source in $installMap.Keys) {
        $destination = Join-Path $root $installMap[$source]
        Write-Utf8NoBom $destination (Read-Utf8 $source)
    }

    & 'vendor\bin\pint' 'routes\web.php' 'routes\admin_ui_m5.php' 'tests\Feature\AdminUi\AdminSupportManagementUiTest.php' 'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php' --quiet | Out-Host
    Assert-LastExit 'M5 Pint formatting failed.'

    Assert-PreviousUiUnchanged $previousHashes $sidebarRel

    php artisan route:clear | Out-Host
    Assert-LastExit 'Laravel route cache clear failed.'
    php artisan view:clear | Out-Host
    Assert-LastExit 'Laravel view cache clear failed.'

    node (Join-Path $PSScriptRoot 'verify-support-ui-contract.mjs') $root | Out-Host
    Assert-LastExit 'M5 static contract verification failed.'

    php artisan test tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php | Out-Host
    Assert-LastExit 'M5 rendering smoke test failed.'
    php artisan test tests\Feature\AdminUi\AdminSupportManagementUiTest.php | Out-Host
    Assert-LastExit 'M5 support UI test failed.'
    php artisan test tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php | Out-Host
    Assert-LastExit 'M4 rendering regression failed after M5 installation.'
    php artisan test tests\Feature\AdminUi\AdminModerationReportsUiTest.php | Out-Host
    Assert-LastExit 'M4 moderation UI regression failed after M5 installation.'
    php artisan test tests\Feature\Api\Admin\V1\AdminPrivacyComplianceSupportTest.php | Out-Host
    Assert-LastExit 'Support backend regression failed after M5 installation.'

    Assert-PreviousUiUnchanged $previousHashes $sidebarRel
} catch {
    Write-Host ''
    Write-Host 'M5 installation failed. Restoring the pre-install source checkpoint...' -ForegroundColor Red
    Restore-M5Backup
    throw
}

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 5 Support Management installed.' -ForegroundColor Green
Write-Host "Backup: $backup"
Write-Host 'Previous M1-M4 UI source files were hash-checked and remained unchanged.' -ForegroundColor Green
Write-Host 'Next recommended command:'
Write-Host '  .\setup\admin-ui-m5\verify-admin-ui-m5.ps1 -FullRegression'

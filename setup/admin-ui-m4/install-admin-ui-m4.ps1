$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Read-Utf8([string]$Path) {
    $resolved = (Resolve-Path $Path).Path
    return [System.IO.File]::ReadAllText($resolved, [System.Text.Encoding]::UTF8)
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    [System.IO.File]::WriteAllText($Path, $Content, [System.Text.UTF8Encoding]::new($false))
}

function Repair-CanonicalSidebarIcons([string]$Content) {
    $icons = [ordered]@{
        'Dashboard' = ([string][char]0x2302)
        'Users' = ([string][char]0x25CE)
        'Circles' = ([string][char]0x25CC)
        'Safety\s*/\s*SOS' = ([string][char]0x271A)
        'Moderation\s*&(?:amp;)?\s*Reports' = ([string][char]0x25C7)
        'Support' = '?'
        'Subscriptions\s*&(?:amp;)?\s*Payments' = '$'
        'Advertising' = ([string][char]0x25A3)
        'Notifications\s*&(?:amp;)?\s*Announcements' = ([string][char]0x25C8)
        'Analytics' = ([string][char]0x2301)
        'Privacy\s*&(?:amp;)?\s*Compliance' = ([string][char]0x26BF)
        'Security' = ([string][char]0x25C6)
        'Content' = ([string][char]0x25A4)
        'Feature\s+Flags\s*&(?:amp;)?\s*Configuration' = ([string][char]0x2699)
        'System\s+Operations' = ([string][char]0x25EB)
        'Audit\s+Logs' = ([string][char]0x2261)
        'Administrators' = ([string][char]0x2659)
    }

    foreach ($labelPattern in $icons.Keys) {
        $pattern = '(?s)(<span\s+class=["'']orbit-nav-icon["''][^>]*>)[^<]*(</span>\s*<span(?:\s+[^>]*)?>\s*' + $labelPattern + '\s*</span>)'
        $regex = [regex]::new($pattern)
        $matches = $regex.Matches($Content)
        if ($matches.Count -ne 1) {
            throw "Expected exactly one sidebar icon for $labelPattern, found $($matches.Count). Refusing an unsafe rewrite."
        }
        $icon = [string]$icons[$labelPattern]
        $evaluator = [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $match.Groups[1].Value + $icon + $match.Groups[2].Value }
        $Content = $regex.Replace($Content, $evaluator, 1)
    }

    # Theme is a footer control rather than a normal destination, but it uses the same icon class.
    $themePattern = '(?s)(<span\s+class=["'']orbit-nav-icon["''][^>]*>)[^<]*(</span>\s*<span\s+data-theme-label[^>]*>\s*Theme\s*:)'
    $themeRegex = [regex]::new($themePattern)
    $themeMatches = $themeRegex.Matches($Content)
    if ($themeMatches.Count -eq 1) {
        $themeEvaluator = [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $match.Groups[1].Value + ([string][char]0x25D0) + $match.Groups[2].Value }
        $Content = $themeRegex.Replace($Content, $themeEvaluator, 1)
    }

    return $Content
}

$requiredBase = @(
    'resources\js\admin-console\index.js',
    'setup\admin-ui-m3\verify-admin-ui-m3.ps1',
    'tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php'
)
foreach ($path in $requiredBase) {
    if (-not (Test-Path $path)) { throw "Canonical M1-M3 admin UI prerequisite is missing: $path" }
}

$routeJson = php artisan route:list --json
if ($LASTEXITCODE -ne 0) { throw 'Unable to inspect the live Laravel route inventory.' }
$routes = $routeJson | ConvertFrom-Json
if (-not ($routes | Where-Object { $_.name -eq 'admin.console.operations.sos.index' })) {
    throw 'Canonical M3 SOS web route is missing. Verify M3 before installing M4.'
}

$controllerMap = [ordered]@{
    reportList='ListModerationReportsController'; reportShow='ShowModerationReportController'; reportAssign='AssignModerationReportController';
    reportWorkflow='UpdateModerationReportWorkflowController'; reportNote='AddModerationCaseNoteController'; reportEnforce='ApplyModerationEnforcementController';
    appealList='ListModerationAppealsController'; appealShow='ShowModerationAppealController'; appealAssign='AssignModerationAppealController';
    appealReview='ReviewModerationAppealController'; appealSecondReview='SecondReviewModerationAppealController'; riskList='ListRiskProfilesController';
    riskShow='ShowRiskProfileController'; riskCreate='CreateRiskSignalController'; riskResolve='ResolveRiskSignalController'
}

function Get-RouteByController([string]$suffix) {
    $match = $routes | Where-Object { [string]$_.action -like "*$suffix*" } | Select-Object -First 1
    if (-not $match) { throw "Required moderation backend route is missing: $suffix" }
    return $match
}

function Normalize-Uri([string]$uri, [string]$key) {
    $matches = [regex]::Matches($uri, '\{[^}]+\}')
    if ($matches.Count -eq 0) { return '/' + $uri.TrimStart('/') }
    $normalized = $uri
    if ($key -like 'report*') {
        $normalized = [regex]::Replace($normalized, '\{[^}]+\}', '{reportId}', 1)
    } elseif ($key -like 'appeal*') {
        $normalized = [regex]::Replace($normalized, '\{[^}]+\}', '{appealId}', 1)
    } elseif ($key -eq 'riskResolve') {
        if ($matches.Count -gt 1) {
            $normalized = [regex]::Replace($normalized, '\{[^}]+\}', '{profileId}', 1)
            $all = [regex]::Matches($normalized, '\{[^}]+\}')
            if ($all.Count -gt 1) {
                $last = $all[$all.Count - 1]
                $normalized = $normalized.Remove($last.Index, $last.Length).Insert($last.Index, '{signalId}')
            } else { $normalized = $normalized -replace '\{profileId\}', '{signalId}' }
        } else { $normalized = [regex]::Replace($normalized, '\{[^}]+\}', '{signalId}', 1) }
    } elseif ($key -like 'risk*') {
        $normalized = [regex]::Replace($normalized, '\{[^}]+\}', '{profileId}', 1)
    }
    return '/' + $normalized.TrimStart('/')
}

$routeContract = [ordered]@{}
foreach ($key in $controllerMap.Keys) {
    $route = Get-RouteByController $controllerMap[$key]
    $method = ([string]$route.method -split '\|' | Where-Object { $_ -ne 'HEAD' } | Select-Object -First 1)
    $routeContract[$key] = [ordered]@{ method=$method; uri=(Normalize-Uri ([string]$route.uri) $key) }
}
$reauth = $routes | Where-Object { [string]$_.uri -eq 'api/admin/v1/auth/reauthenticate' -or [string]$_.name -eq 'api.admin.v1.auth.reauthenticate' } | Select-Object -First 1
if (-not $reauth) { throw 'Administrator reauthentication route is missing.' }
$routeContract.reauthenticate = [ordered]@{ method=(([string]$reauth.method -split '\|' | Where-Object { $_ -ne 'HEAD' } | Select-Object -First 1)); uri=('/' + ([string]$reauth.uri).TrimStart('/')) }

$sidebarFile = Get-ChildItem 'resources\views' -Recurse -Filter '*.blade.php' | Where-Object { $content = Read-Utf8 $_.FullName; $content -match 'orbit-nav-item' -and $content -match 'Moderation\s*&(?:amp;)?\s*Reports' } | Select-Object -First 1
if (-not $sidebarFile) { throw 'Could not locate the canonical sidebar containing Moderation & Reports.' }
$rootPrefix = $root.TrimEnd('\','/') + [IO.Path]::DirectorySeparatorChar
if (-not $sidebarFile.FullName.StartsWith($rootPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Canonical sidebar is outside the project root: $($sidebarFile.FullName)"
}
$sidebarRel = $sidebarFile.FullName.Substring($rootPrefix.Length)

$backup = Join-Path $root ('storage\app\orbit-admin-ui-m4-backups\' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$preExisting = @('routes\web.php','resources\js\admin-console\index.js')
$preExisting += $sidebarRel
foreach ($rel in $preExisting) {
    $src = Join-Path $root $rel; $dst = Join-Path $backup $rel
    New-Item -ItemType Directory -Force -Path (Split-Path $dst -Parent) | Out-Null
    Copy-Item $src $dst -Force
}
Write-Utf8NoBom (Join-Path $backup 'manifest.json') (($preExisting | ConvertTo-Json) + "`n")

# M4 v1-v5 may have touched the sidebar while running under Windows PowerShell 5.1.
# Repair only the known icon glyphs by their semantic labels. Do not re-encode or broadly
# rewrite unrelated sidebar content.
$currentSidebar = Read-Utf8 $sidebarFile.FullName
$repairedSidebar = Repair-CanonicalSidebarIcons $currentSidebar
if ($repairedSidebar -ne $currentSidebar) {
    Write-Utf8NoBom $sidebarFile.FullName $repairedSidebar
    Write-Host 'Restored canonical sidebar icons.' -ForegroundColor Yellow
}

# Reuse the real canonical layout used by M3 instead of introducing another shell.
$sosView = Get-ChildItem 'resources\views' -Recurse -Filter '*.blade.php' | Where-Object { (Read-Utf8 $_.FullName) -match 'data-orbit-view="sos-(index|show)"' } | Select-Object -First 1
if (-not $sosView) { throw 'Could not locate a canonical M3 SOS view.' }
$sosSource = Read-Utf8 $sosView.FullName
$layoutMatch = [regex]::Match($sosSource, '@extends\([''"]([^''"]+)[''"]\)')
if (-not $layoutMatch.Success) { throw 'Could not safely discover the canonical Blade layout from M3.' }
$sectionMatch = [regex]::Match($sosSource, '@section\([''"]([^''"]+)[''"]\)')
if (-not $sectionMatch.Success) { throw 'Could not safely discover the canonical Blade content section from M3.' }
$layout = $layoutMatch.Groups[1].Value
$section = $sectionMatch.Groups[1].Value
Get-ChildItem 'resources\views\admin\console\operations\moderation' -Recurse -Filter '*.blade.php' | ForEach-Object {
    $source = Read-Utf8 $_.FullName
    $source = $source.Replace('__ORBIT_LAYOUT__', $layout).Replace('__ORBIT_SECTION__', $section)
    Write-Utf8NoBom $_.FullName $source
}

$web = Read-Utf8 'routes\web.php'
$web = ($web -replace "`r`n", "`n") -replace "`r", "`n"
if ($web -notmatch 'admin_ui_m4\.php') {
    $web = $web.TrimEnd() + "`n`n// ORBIT ADMIN UI M4 START`nrequire __DIR__.'/admin_ui_m4.php';`n// ORBIT ADMIN UI M4 END`n"
}
Write-Utf8NoBom 'routes\web.php' $web
$entry = Read-Utf8 'resources\js\admin-console\index.js'
if ($entry -notmatch "moderation-m4\.js") {
    Write-Utf8NoBom 'resources\js\admin-console\index.js' ("import './moderation-m4.js';`r`n" + $entry)
}

$sidebar = Read-Utf8 $sidebarFile.FullName
$new = @'
<a href="{{ route('admin.console.operations.moderation.index') }}" class="orbit-nav-item {{ request()->routeIs('admin.console.operations.moderation.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.console.operations.moderation.*')) aria-current="page" @endif>
                <span class="orbit-nav-icon" aria-hidden="true">__MODERATION_ICON__</span><span>Moderation &amp; Reports</span>
            </a>
'@
$new = $new.Replace('__MODERATION_ICON__', [string][char]0x25C7).Trim()

# Be idempotent across fresh M3, M4 v1-v6, and already-active M4 sidebars. Replace exactly one
# Moderation item whether it is the old disabled placeholder or an existing active link.
$activePattern = @'
(?s)<a\b(?=[^>]*class=["'][^"']*\borbit-nav-item\b[^"']*["'])[^>]*>(?:(?!</a>).)*?<span(?:\s+[^>]*)?>\s*Moderation\s*&(?:amp;)?\s*Reports\s*</span>(?:(?!</a>).)*?</a>
'@.Trim()
$disabledPattern = @'
(?s)<span\s+class=["'][^"']*\borbit-nav-item\b[^"']*\borbit-nav-item--disabled\b[^"']*["'][^>]*>\s*<span\s+class=["']orbit-nav-icon["'][^>]*>[^<]*</span>\s*<span(?:\s+[^>]*)?>\s*Moderation\s*&(?:amp;)?\s*Reports\s*</span>\s*<small>.*?</small>\s*</span>
'@.Trim()
$activeMatches = [regex]::Matches($sidebar, $activePattern)
$disabledMatches = [regex]::Matches($sidebar, $disabledPattern)
$totalModerationItems = $activeMatches.Count + $disabledMatches.Count
if ($totalModerationItems -ne 1) {
    throw "Expected exactly one canonical Moderation sidebar item, found $totalModerationItems. Refusing an unsafe rewrite."
}
if ($activeMatches.Count -eq 1) {
    $sidebar = [regex]::Replace($sidebar, $activePattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $new }, 1)
} else {
    $sidebar = [regex]::Replace($sidebar, $disabledPattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $new }, 1)
}
Write-Utf8NoBom $sidebarFile.FullName $sidebar

$storageCandidates = @()
Get-ChildItem 'resources\js\admin-console' -Filter '*.js' | Where-Object { $_.Name -notin @('moderation-m4.js','moderation-routes.generated.js') } | ForEach-Object {
    $source = Read-Utf8 $_.FullName
    foreach ($m in [regex]::Matches($source, '(?<storage>sessionStorage|localStorage)\.getItem\(\s*[''"](?<key>[^''"]+)[''"]\s*\)')) {
        if ($m.Groups['key'].Value -match '(?i)admin|token|auth|session') { $storageCandidates += [ordered]@{storage=$m.Groups['storage'].Value;key=$m.Groups['key'].Value} }
    }
}
$storageCandidates = @($storageCandidates | Sort-Object storage,key -Unique)
$actions = @('warn_user','restrict_feature','suspend_user_temp','suspend_user_indefinite','freeze_circle')
$service = 'app\Modules\Admin\Moderation\Services\ModerationEnforcementService.php'
if (Test-Path $service) {
    $source = Read-Utf8 $service
    $found = @($actions | Where-Object { $source.Contains("'$_'") -or $source.Contains('"' + $_ + '"') })
    if ($found.Count -gt 0) { $actions = $found }
}
$config = [ordered]@{ enforcementActions=$actions; storageCandidates=$storageCandidates }
$routeJs = 'export const moderationRoutes = ' + ($routeContract | ConvertTo-Json -Depth 6 -Compress) + ";`r`nexport const moderationConfig = " + ($config | ConvertTo-Json -Depth 6 -Compress) + ";`r`n"
Write-Utf8NoBom 'resources\js\admin-console\moderation-routes.generated.js' $routeJs

# Format only the PHP files introduced or modified by M4 using the project's canonical Pint rules.
& 'vendor\bin\pint' 'routes\web.php' 'routes\admin_ui_m4.php' 'tests\Feature\AdminUi\AdminModerationReportsUiTest.php' --quiet | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'M4 Pint formatting repair failed.' }

php artisan route:clear | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Laravel route cache clear failed.' }
php artisan view:clear | Out-Host
if ($LASTEXITCODE -ne 0) { throw 'Laravel view cache clear failed.' }

Write-Host ''
Write-Host 'Orbit Admin UI Milestone 4 Moderation / Appeals / Risk installed.' -ForegroundColor Green
Write-Host "Backup: $backup"
Write-Host 'Next recommended command:'
Write-Host '  .\setup\admin-ui-m4\verify-admin-ui-m4.ps1 -FullRegression'

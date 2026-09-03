$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $root

function Read-Utf8([string]$Path) {
    $resolved = (Resolve-Path $Path).Path
    return [System.IO.File]::ReadAllText($resolved, [System.Text.Encoding]::UTF8)
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    [System.IO.File]::WriteAllText($Path, $Content, (New-Object System.Text.UTF8Encoding($false)))
}

function Has-CanonicalSidebarStructure([string]$Content) {
    $required = @(
        'Dashboard', 'Users', 'Circles', 'Safety\s*/\s*SOS',
        'Moderation\s*&(?:amp;)?\s*Reports', 'Support',
        'Subscriptions\s*&(?:amp;)?\s*Payments', 'Advertising',
        'Notifications\s*&(?:amp;)?\s*Announcements', 'Analytics',
        'Privacy\s*&(?:amp;)?\s*Compliance', 'Security', 'Content',
        'Feature\s+Flags\s*&(?:amp;)?\s*Configuration',
        'System\s+Operations', 'Audit\s+Logs', 'Administrators'
    )

    if ($Content -notmatch 'orbit-sidebar__nav') { return $false }
    if ($Content -notmatch 'orbit-sidebar__footer') { return $false }
    if ($Content -notmatch 'orbit-sidebar__principle') { return $false }
    if ($Content -notmatch 'admin\.console\.operations\.moderation\.index') { return $false }

    foreach ($pattern in $required) {
        if ($Content -notmatch $pattern) { return $false }
    }

    return $true
}

function Repair-CanonicalIcons([string]$Content) {
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
        # Strictly local: icon text may not cross another HTML tag before the adjacent label span.
        $pattern = '(?s)(<span\s+class=["'']orbit-nav-icon["''][^>]*>)[^<]*(</span>\s*<span(?:\s+[^>]*)?>\s*' + $labelPattern + '\s*</span>)'
        $regex = New-Object System.Text.RegularExpressions.Regex($pattern)
        $matches = $regex.Matches($Content)
        if ($matches.Count -ne 1) {
            throw "Recovery source is not canonical for sidebar label $labelPattern. Found $($matches.Count) adjacent icon/label pairs."
        }
        $icon = [string]$icons[$labelPattern]
        $evaluator = [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            return $match.Groups[1].Value + $icon + $match.Groups[2].Value
        }
        $Content = $regex.Replace($Content, $evaluator, 1)
    }

    $themePattern = '(?s)(<span\s+class=["'']orbit-nav-icon["''][^>]*>)[^<]*(</span>\s*<span\s+data-theme-label[^>]*>\s*Theme\s*:)'
    $themeRegex = New-Object System.Text.RegularExpressions.Regex($themePattern)
    $themeMatches = $themeRegex.Matches($Content)
    if ($themeMatches.Count -ne 1) {
        throw "Recovery source must contain exactly one Theme icon, found $($themeMatches.Count)."
    }
    $themeIcon = [string][char]0x25D0
    $themeEvaluator = [System.Text.RegularExpressions.MatchEvaluator]{
        param($match)
        return $match.Groups[1].Value + $themeIcon + $match.Groups[2].Value
    }
    $Content = $themeRegex.Replace($Content, $themeEvaluator, 1)

    return $Content
}

$backupRoot = Join-Path $root 'storage\app\orbit-admin-ui-m4-backups'
if (-not (Test-Path $backupRoot)) {
    throw 'M4 backup directory is missing. No recovery changes were made.'
}

$selected = $null
$backupDirs = Get-ChildItem $backupRoot -Directory | Sort-Object Name -Descending
foreach ($dir in $backupDirs) {
    $webBackup = Join-Path $dir.FullName 'routes\web.php'
    $indexBackup = Join-Path $dir.FullName 'resources\js\admin-console\index.js'
    if (-not (Test-Path $webBackup) -or -not (Test-Path $indexBackup)) { continue }

    $webText = Read-Utf8 $webBackup
    $indexText = Read-Utf8 $indexBackup
    if ($webText -notmatch 'admin_ui_m4\.php') { continue }
    if ($indexText -notmatch 'moderation-m4\.js') { continue }

    $sidebarCandidates = Get-ChildItem $dir.FullName -Recurse -Filter 'sidebar.blade.php' -File
    foreach ($sidebarCandidate in $sidebarCandidates) {
        $sidebarText = Read-Utf8 $sidebarCandidate.FullName
        if (Has-CanonicalSidebarStructure $sidebarText) {
            $selected = [pscustomobject]@{
                Backup = $dir
                Sidebar = $sidebarCandidate
                SidebarText = $sidebarText
                WebText = $webText
                IndexText = $indexText
            }
            break
        }
    }
    if ($selected) { break }
}

if (-not $selected) {
    throw 'Could not find an intact post-M4 shared-shell backup. No recovery changes were made.'
}

$backupPrefix = $selected.Backup.FullName.TrimEnd('\','/') + [IO.Path]::DirectorySeparatorChar
if (-not $selected.Sidebar.FullName.StartsWith($backupPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Selected sidebar backup is outside its backup directory.'
}
$sidebarRel = $selected.Sidebar.FullName.Substring($backupPrefix.Length)
$sidebarTarget = Join-Path $root $sidebarRel

$repairedSidebar = Repair-CanonicalIcons $selected.SidebarText
if (-not (Has-CanonicalSidebarStructure $repairedSidebar)) {
    throw 'Recovered sidebar failed structural validation before write. No recovery changes were made.'
}

# Validate shared M4 wiring before touching the project.
$moderationImports = ([regex]::Matches($selected.IndexText, 'moderation-m4\.js')).Count
$moderationRequires = ([regex]::Matches($selected.WebText, 'admin_ui_m4\.php')).Count
if ($moderationImports -ne 1) {
    throw "Selected recovery backup has $moderationImports moderation imports; expected exactly 1."
}
if ($moderationRequires -ne 1) {
    throw "Selected recovery backup has $moderationRequires M4 route requires; expected exactly 1."
}

$emergency = Join-Path $root ('storage\app\orbit-admin-ui-m4-recovery-backups\' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $emergency | Out-Null
$currentFiles = @('routes\web.php', 'resources\js\admin-console\index.js', $sidebarRel)
foreach ($rel in $currentFiles) {
    $source = Join-Path $root $rel
    if (Test-Path $source) {
        $dest = Join-Path $emergency $rel
        New-Item -ItemType Directory -Force -Path (Split-Path $dest -Parent) | Out-Null
        Copy-Item $source $dest -Force
    }
}

try {
    Write-Utf8NoBom (Join-Path $root 'routes\web.php') $selected.WebText
    Write-Utf8NoBom (Join-Path $root 'resources\js\admin-console\index.js') $selected.IndexText
    New-Item -ItemType Directory -Force -Path (Split-Path $sidebarTarget -Parent) | Out-Null
    Write-Utf8NoBom $sidebarTarget $repairedSidebar

    node '.\setup\admin-ui-m4\verify-admin-ui-m4-recovery.mjs' | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'Recovery static verification failed.' }

    php artisan route:clear | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'Laravel route cache clear failed.' }
    php artisan view:clear | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'Laravel view cache clear failed.' }
} catch {
    foreach ($rel in $currentFiles) {
        $saved = Join-Path $emergency $rel
        $target = Join-Path $root $rel
        if (Test-Path $saved) {
            New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
            Copy-Item $saved $target -Force
        }
    }
    throw
}

Write-Host ''
Write-Host 'Orbit M4 shared shell recovered from the last intact post-M4 backup.' -ForegroundColor Green
Write-Host "Recovery source: $($selected.Backup.FullName)"
Write-Host "Emergency pre-recovery backup: $emergency"
Write-Host 'Next recommended command:'
Write-Host '  .\setup\admin-ui-m4\verify-admin-ui-m4-recovery.ps1'

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
$setupRoot = $PSScriptRoot
$payloadRoot = Join-Path $setupRoot 'payload'
$utf8NoBom = [System.Text.UTF8Encoding]::new($false)

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this installer from the Orbit Laravel project root (the directory containing artisan).'
}

$requiredFiles = @(
    'routes\web.php',
    'bootstrap\app.php',
    'resources\css\app.css',
    'resources\js\app.js',
    'vite.config.js',
    'tests\Feature\AdminUi\AdminUiFoundationTest.php'
)

foreach ($relativePath in $requiredFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath))) {
        throw "Required Orbit file not found: $relativePath"
    }
}

if (-not (Test-Path -LiteralPath $payloadRoot)) {
    throw "Consolidation payload not found: $payloadRoot"
}

function Read-TextFile {
    param([Parameter(Mandatory = $true)][string]$Path)
    return [System.IO.File]::ReadAllText($Path)
}

function Write-Utf8Lf {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $normalized = $Content.Replace("`r`n", "`n").Replace("`r", "`n")
    [System.IO.File]::WriteAllText($Path, $normalized, $utf8NoBom)
}

function Remove-LiteralLine {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$Line
    )

    $escaped = [regex]::Escape($Line)
    return [regex]::Replace($Content, "(?m)^[ `t]*$escaped[ `t]*`r?`n?", '')
}

# Preflight Vite before touching the project. This installer supports the Laravel
# plugin's normal input array and deliberately stops instead of guessing if the
# project uses a materially different build configuration.
$vitePath = Join-Path $projectRoot 'vite.config.js'
$viteContent = Read-TextFile -Path $vitePath
$viteMatch = [regex]::Match(
    $viteContent,
    'input\s*:\s*\[(?<body>[\s\S]*?)\]',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)

if (-not $viteMatch.Success) {
    throw 'Could not find the Laravel Vite input array. Nothing was changed. Review vite.config.js before installing.'
}

# Inspect the existing Foundation authentication source before installing the
# canonical console. We collect browser-storage key names from source code only;
# no credentials or token values are read. This lets M2 consume the already-
# established M1 admin session without network interception or a second login.
$detectedFoundationTokenKeys = @()
$storageWritePattern = [regex]::new(
    '(?:window\.)?(?:sessionStorage|localStorage)\.setItem\(\s*["''](?<key>[^"'']+)["'']\s*,',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
$namedStorageKeyPattern = [regex]::new(
    '(?<name>[A-Z0-9_]*(?:ADMIN|AUTH|SESSION|TOKEN|CREDENTIAL)[A-Z0-9_]*)\s*=\s*["''](?<key>[^"'']+)["'']',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)

$foundationSourceFiles = @()
$existingJsRoot = Join-Path $projectRoot 'resources\js'
if (Test-Path -LiteralPath $existingJsRoot) {
    $foundationSourceFiles += Get-ChildItem -LiteralPath $existingJsRoot -File -Recurse -Filter '*.js' -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -notmatch '[\\/]admin-console[\\/]' -and $_.FullName -notmatch '[\\/]admin-ui-m2[\\/]' }
}
$existingAdminViewsRoot = Join-Path $projectRoot 'resources\views\admin'
if (Test-Path -LiteralPath $existingAdminViewsRoot) {
    $foundationSourceFiles += Get-ChildItem -LiteralPath $existingAdminViewsRoot -File -Recurse -Filter '*.blade.php' -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -notmatch '[\\/]operations[\\/]layouts[\\/]shell\.blade\.php$' }
}

foreach ($sourceFile in $foundationSourceFiles) {
    $source = Read-TextFile -Path $sourceFile.FullName

    foreach ($match in $storageWritePattern.Matches($source)) {
        $key = $match.Groups['key'].Value
        $contextStart = [Math]::Max(0, $match.Index - 320)
        $contextLength = [Math]::Min($source.Length - $contextStart, $match.Length + 640)
        $context = $source.Substring($contextStart, $contextLength)

        if ($context -match '(?i)admin|access[_-]?token|bearer|mfa') {
            if ($detectedFoundationTokenKeys -notcontains $key) {
                $detectedFoundationTokenKeys += $key
            }
        }
    }

    # Foundation may assign its browser-storage key to a named constant before
    # calling setItem with that variable. Collect only admin-auth shaped key names.
    if ($sourceFile.FullName -match '(?i)admin' -or $source -match '(?i)administrator|admin/v1/auth|admin login') {
        foreach ($match in $namedStorageKeyPattern.Matches($source)) {
            $key = $match.Groups['key'].Value
            if ($key -match '(?i)admin|orbit|auth|session|token|credential') {
                if ($detectedFoundationTokenKeys -notcontains $key) {
                    $detectedFoundationTokenKeys += $key
                }
            }
        }
    }
}

# Rollback checkpoints are operational artifacts, not application source. Keep
# them under storage/app so Laravel Pint and other source scanners cannot treat old
# snapshots as current code. Migrate checkpoints created by v1-v6 out of setup/.
$backupStorageRoot = Join-Path $projectRoot 'storage\app\orbit-admin-ui-consolidation-backups'
New-Item -ItemType Directory -Path $backupStorageRoot -Force | Out-Null

$legacyBackupsRoot = Join-Path $setupRoot 'backups'
if (Test-Path -LiteralPath $legacyBackupsRoot) {
    Get-ChildItem -LiteralPath $legacyBackupsRoot -Directory -Force | ForEach-Object {
        $destination = Join-Path $backupStorageRoot $_.Name
        if (Test-Path -LiteralPath $destination) {
            $destination = Join-Path $backupStorageRoot ($_.Name + '-legacy-' + (Get-Date -Format 'yyyyMMddHHmmssfff'))
        }
        Move-Item -LiteralPath $_.FullName -Destination $destination -Force
    }

    if (-not (Get-ChildItem -LiteralPath $legacyBackupsRoot -Force -ErrorAction SilentlyContinue)) {
        Remove-Item -LiteralPath $legacyBackupsRoot -Force
    }
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupRoot = Join-Path $backupStorageRoot $stamp
$backupFiles = Join-Path $backupRoot 'files'
New-Item -ItemType Directory -Path $backupFiles -Force | Out-Null

function Backup-Path {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $source = Join-Path $projectRoot $RelativePath
    if (-not (Test-Path -LiteralPath $source)) { return }

    $destination = Join-Path $backupFiles $RelativePath
    $parent = Split-Path -Path $destination -Parent
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
    Copy-Item -LiteralPath $source -Destination $destination -Recurse -Force
}

$backupPaths = @(
    'routes\web.php',
    'vite.config.js',
    'bootstrap\app.php',
    'resources\css\app.css',
    'resources\js\app.js',
    'resources\views\admin',
    'resources\css\admin-console.css',
    'resources\js\admin-console',
    'routes\admin_console.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
    'tests\Feature\AdminUi\AdminUiFoundationTest.php',
    'resources\css\admin-ui-m2.css',
    'resources\js\admin-ui-m2',
    'routes\admin_ui_m2.php',
    'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php',
    'public\orbit-admin-m2-foundation-bridge.js',
    'tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
)

foreach ($relativePath in $backupPaths) {
    Backup-Path -RelativePath $relativePath
}

$backupInfo = @(
    'Orbit Admin UI M1 + M2 consolidation backup',
    "Created: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss K')",
    "Project root: $projectRoot",
    'No database data or migrations are modified by this installer.'
) -join "`n"
Write-Utf8Lf -Path (Join-Path $backupRoot 'BACKUP_INFO.txt') -Content ($backupInfo + "`n")

# Record the current checkpoint before the first destructive change so a bare
# rollback command always targets this installation attempt, even if a later
# structural postcondition stops the installer.
$lastBackupPath = Join-Path $setupRoot '.last-backup.txt'
Write-Utf8Lf -Path $lastBackupPath -Content ($backupRoot + "`n")

$legacyPaths = @(
    'resources\views\admin\operations\layouts\shell.blade.php',
    'resources\css\admin-ui-m2.css',
    'resources\js\admin-ui-m2',
    'routes\admin_ui_m2.php',
    'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php',
    'public\orbit-admin-m2-foundation-bridge.js',
    'tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
)

foreach ($relativePath in $legacyPaths) {
    $target = Join-Path $projectRoot $relativePath
    if (Test-Path -LiteralPath $target) {
        Remove-Item -LiteralPath $target -Recurse -Force
    }
}

# Install the canonical Blade/CSS/JS route/view/test payload file-by-file so
# existing project directories are merged predictably on Windows PowerShell 5.1
# instead of relying on Copy-Item directory destination semantics.
Get-ChildItem -LiteralPath $payloadRoot -File -Recurse -Force | ForEach-Object {
    $relativePath = $_.FullName.Substring($payloadRoot.Length) -replace '^[\\/]+', ''
    $destination = Join-Path $projectRoot $relativePath
    $parent = Split-Path -Path $destination -Parent
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
    Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
}

# Materialize the exact Foundation storage key names detected above into a
# generated module. It contains key names only, never token values or credentials.
$foundationKeyModulePath = Join-Path $projectRoot 'resources\js\admin-console\foundation-auth-keys.js'
$foundationKeysLiteral = ConvertTo-Json -InputObject @($detectedFoundationTokenKeys) -Compress
$foundationKeyModule = @(
    '// Generated by install-admin-ui-consolidation.ps1 from the existing Foundation auth source.',
    '// Contains key names only; never token values or credentials.',
    "export const FOUNDATION_DETECTED_TOKEN_KEYS = $foundationKeysLiteral;"
) -join "`n"
Write-Utf8Lf -Path $foundationKeyModulePath -Content ($foundationKeyModule + "`n")

# routes/web.php: remove the old separate M2 route loader and ensure the
# canonical route file is loaded exactly once at the end. Keeping it last makes
# the consolidated /admin route authoritative if an older M1 inline route is
# still present in this historical checkout.
$webPath = Join-Path $projectRoot 'routes\web.php'
$webContent = Read-TextFile -Path $webPath
$webContent = Remove-LiteralLine -Content $webContent -Line "require __DIR__.'/admin_ui_m2.php';"
$webContent = Remove-LiteralLine -Content $webContent -Line "require __DIR__.'/admin_console.php';"
$webContent = $webContent.TrimEnd() + "`n`nrequire __DIR__.'/admin_console.php';`n"
Write-Utf8Lf -Path $webPath -Content $webContent

# Remove only the obsolete M2 bridge middleware registration; existing project
# middleware remains untouched. The bridge installer inserted the registration
# as its own line, so filter any line containing the obsolete class name rather
# than relying on a single regex shape. This also removes a historical `use`
# import if one was added manually.
$bootstrapPath = Join-Path $projectRoot 'bootstrap\app.php'
$bootstrapLines = [System.IO.File]::ReadAllLines($bootstrapPath)
$bootstrapLines = @($bootstrapLines | Where-Object {
    $_ -notmatch 'InjectAdminUiM2FoundationBridge'
})
$bootstrapContent = ($bootstrapLines -join "`n") + "`n"
Write-Utf8Lf -Path $bootstrapPath -Content $bootstrapContent

# Hard postcondition: never continue with two admin runtimes wired at once.
$bootstrapAfterCleanup = Read-TextFile -Path $bootstrapPath
if ($bootstrapAfterCleanup.Contains('InjectAdminUiM2FoundationBridge')) {
    throw "Could not remove the obsolete Admin UI M2 bridge registration from bootstrap/app.php. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

# The canonical console is a dedicated Vite entry. Remove old M2 imports from
# the generic app bundle so there is one visual/runtime architecture.
$appCssPath = Join-Path $projectRoot 'resources\css\app.css'
$appCssContent = Read-TextFile -Path $appCssPath
$appCssContent = [regex]::Replace($appCssContent, '(?m)^[^\r\n]*admin-ui-m2\.css[^\r\n]*\r?\n?', '')
Write-Utf8Lf -Path $appCssPath -Content $appCssContent

$appJsPath = Join-Path $projectRoot 'resources\js\app.js'
$appJsContent = Read-TextFile -Path $appJsPath
$appJsContent = [regex]::Replace($appJsContent, '(?m)^[^\r\n]*admin-ui-m2[^\r\n]*\r?\n?', '')
Write-Utf8Lf -Path $appJsPath -Content $appJsContent

# Keep the historical M1 Foundation UI regression meaningful after the
# canonical shell migration. We deliberately update only the two assertions
# that referenced superseded presentation copy/assets; login, MFA, security
# headers, alias navigation, and privacy assertions remain untouched.
$foundationUiTestPath = Join-Path $projectRoot 'tests\Feature\AdminUi\AdminUiFoundationTest.php'
$foundationUiTestContent = Read-TextFile -Path $foundationUiTestPath
$legacySearchAssertion = "->assertSee('Search users, Circles, incidents', false)"
$canonicalSearchAssertion = "->assertSee('Search Orbit administration', false)"
$legacyDashboardAssetAssertion = "->assertSee('admin-ui/js/pages/dashboard.js', false)"
$canonicalDashboardAssetAssertion = "->assertSee('resources/js/admin-console/index.js', false)"

if ($foundationUiTestContent.Contains($legacySearchAssertion)) {
    $foundationUiTestContent = $foundationUiTestContent.Replace($legacySearchAssertion, $canonicalSearchAssertion)
} elseif (-not $foundationUiTestContent.Contains($canonicalSearchAssertion)) {
    throw "AdminUiFoundationTest.php no longer contains the known M1 search assertion shape. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

if ($foundationUiTestContent.Contains($legacyDashboardAssetAssertion)) {
    $foundationUiTestContent = $foundationUiTestContent.Replace($legacyDashboardAssetAssertion, $canonicalDashboardAssetAssertion)
} elseif (-not $foundationUiTestContent.Contains($canonicalDashboardAssetAssertion)) {
    throw "AdminUiFoundationTest.php no longer contains the known M1 dashboard asset assertion shape. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

Write-Utf8Lf -Path $foundationUiTestPath -Content $foundationUiTestContent

$foundationUiTestAfter = Read-TextFile -Path $foundationUiTestPath
if ($foundationUiTestAfter.Contains('Search users, Circles, incidents') -or $foundationUiTestAfter.Contains('admin-ui/js/pages/dashboard.js')) {
    throw "Historical AdminUiFoundationTest.php still contains superseded M1 UI assertions. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $foundationUiTestAfter.Contains('Search Orbit administration') -or -not $foundationUiTestAfter.Contains('resources/js/admin-console/index.js')) {
    throw "Canonical AdminUiFoundationTest.php assertions were not installed. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

# Add dedicated admin-console inputs idempotently.
$viteContent = Read-TextFile -Path $vitePath
$viteMatch = [regex]::Match(
    $viteContent,
    'input\s*:\s*\[(?<body>[\s\S]*?)\]',
    [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
)
if (-not $viteMatch.Success) {
    throw "Vite input array disappeared during installation. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

$bodyGroup = $viteMatch.Groups['body']
$body = $bodyGroup.Value
$entries = @(
    'resources/css/admin-console.css',
    'resources/js/admin-console/index.js'
)
$missingEntries = @()
foreach ($entry in $entries) {
    if (-not $body.Contains($entry)) { $missingEntries += $entry }
}

if ($missingEntries.Count -gt 0) {
    $working = $body.TrimEnd()
    $trailingWhitespace = $body.Substring($working.Length)
    $indentMatch = [regex]::Match($body, '(?m)^([ \t]*)[^\r\n]*resources/')
    $indent = if ($indentMatch.Success) { $indentMatch.Groups[1].Value } else { '            ' }

    if ($working.Trim().Length -gt 0 -and -not $working.TrimEnd().EndsWith(',')) {
        $working += ','
    }

    foreach ($entry in $missingEntries) {
        $working += "`n$indent'$entry',"
    }

    $newBody = $working + $trailingWhitespace
    $viteContent = $viteContent.Substring(0, $bodyGroup.Index) + $newBody + $viteContent.Substring($bodyGroup.Index + $bodyGroup.Length)
    Write-Utf8Lf -Path $vitePath -Content $viteContent
}

# Structural postconditions run before Laravel commands. If one of these fails,
# the installer stops with the exact backup path instead of presenting a partial
# consolidation as successful.
$requiredCanonicalAfterInstall = @(
    'resources\css\admin-console.css',
    'resources\js\admin-console\index.js',
    'resources\js\admin-console\api-client.js',
    'resources\js\admin-console\auth-session.js',
    'resources\js\admin-console\shell.js',
    'resources\js\admin-console\dashboard.js',
    'resources\js\admin-console\foundation-auth-keys.js',
    'resources\views\admin\layouts\app.blade.php',
    'resources\views\admin\partials\sidebar.blade.php',
    'resources\views\admin\partials\topbar.blade.php',
    'resources\views\admin\dashboard.blade.php',
    'resources\views\admin\operations\users\index.blade.php',
    'resources\views\admin\operations\users\show.blade.php',
    'resources\views\admin\operations\circles\index.blade.php',
    'resources\views\admin\operations\circles\show.blade.php',
    'routes\admin_console.php'
)
foreach ($relativePath in $requiredCanonicalAfterInstall) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath))) {
        throw "Canonical Admin Console file was not installed: $relativePath. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
    }
}

$forbiddenLegacyAfterInstall = @(
    'resources\views\admin\operations\layouts\shell.blade.php',
    'resources\css\admin-ui-m2.css',
    'resources\js\admin-ui-m2',
    'routes\admin_ui_m2.php',
    'app\Http\Middleware\InjectAdminUiM2FoundationBridge.php',
    'public\orbit-admin-m2-foundation-bridge.js',
    'tests\Feature\AdminUi\AdminUiM2SmokeTest.php'
)
foreach ($relativePath in $forbiddenLegacyAfterInstall) {
    if (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath)) {
        throw "Obsolete Admin UI M2 artifact is still present: $relativePath. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
    }
}

$canonicalLayoutAfterInstall = Read-TextFile -Path (Join-Path $projectRoot 'resources\views\admin\layouts\app.blade.php')
$canonicalShellAfterInstall = Read-TextFile -Path (Join-Path $projectRoot 'resources\js\admin-console\shell.js')
$canonicalSessionAfterInstall = Read-TextFile -Path (Join-Path $projectRoot 'resources\js\admin-console\auth-session.js')
$canonicalClientAfterInstall = Read-TextFile -Path (Join-Path $projectRoot 'resources\js\admin-console\api-client.js')
$canonicalDashboardAfterInstall = Read-TextFile -Path (Join-Path $projectRoot 'resources\js\admin-console\dashboard.js')

if ($canonicalLayoutAfterInstall.Contains('data-admin-auth-dialog') -or $canonicalLayoutAfterInstall.Contains('data-admin-auth-form')) {
    throw "The duplicate consolidated administrator login dialog is still present. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalLayoutAfterInstall.Contains('data-orbit-auth-owner="foundation"')) {
    throw "The canonical shell is not delegated to Foundation authentication. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalLayoutAfterInstall.Contains('data-auth-gate')) {
    throw "The canonical shell is missing its administrator session verification gate. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
foreach ($forbiddenAuthRuntime in @('/api/admin/v1/auth/login', '/api/admin/v1/auth/mfa/verify', 'writeAdminSession')) {
    if ($canonicalShellAfterInstall.Contains($forbiddenAuthRuntime)) {
        throw "The canonical shell still owns administrator login/MFA logic: $forbiddenAuthRuntime. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
    }
}
if (-not $canonicalShellAfterInstall.Contains("const LOGIN_PATH = '/admin/login';")) {
    throw "The canonical shell is missing its Foundation login redirect. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalShellAfterInstall.Contains("adminApi('/api/admin/v1/auth/me')")) {
    throw "The canonical shell is missing protected administrator session validation. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalSessionAfterInstall.Contains('FOUNDATION_DETECTED_TOKEN_KEYS')) {
    throw "The canonical session reader is missing Foundation storage-key compatibility. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if ($canonicalClientAfterInstall.Contains("throw new OrbitAdminApiError('Administrator authentication is required.'")) {
    throw "The API client still rejects same-origin Foundation cookie authentication before consulting the server. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if ($canonicalSessionAfterInstall.Contains('window.fetch') -or $canonicalSessionAfterInstall.Contains('XMLHttpRequest')) {
    throw "Administrator session compatibility must not intercept browser network traffic. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalDashboardAfterInstall.Contains('data?.snapshot') -or -not $canonicalDashboardAfterInstall.Contains('business.users.total')) {
    throw "The canonical dashboard adapter is not pinned to the completed data.snapshot backend contract. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $canonicalDashboardAfterInstall.Contains('dashboard_contract_mismatch')) {
    throw "The canonical dashboard adapter would not fail visibly on response-contract drift. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

$webAfterInstall = Read-TextFile -Path $webPath
if ($webAfterInstall.Contains('admin_ui_m2.php')) {
    throw "The obsolete admin_ui_m2 route loader is still referenced by routes/web.php. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}
if (-not $webAfterInstall.Contains("require __DIR__.'/admin_console.php';")) {
    throw "The canonical admin_console route loader is missing from routes/web.php. Restore with: .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"
}

& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    throw "Laravel cache clearing failed. Your backup is: $backupRoot"
}

Write-Host ''
Write-Host 'Orbit Admin UI M1 + M2 canonical consolidation installed.'
Write-Host "Backup: $backupRoot"
Write-Host 'Rollback checkpoints are stored outside active source under storage/app.'
Write-Host ''
Write-Host 'Removed after backup:'
Write-Host '  - separate M2 Blade shell'
Write-Host '  - admin-ui-m2 CSS/JavaScript bundle'
Write-Host '  - admin_ui_m2 route layer'
Write-Host '  - M1/M2 token bridge middleware and public bridge script'
Write-Host '  - obsolete M2 shell smoke test'
Write-Host ''
Write-Host 'Installed:'
Write-Host '  - one canonical Blade admin shell'
Write-Host '  - compact dashboard + global search command surface'
Write-Host '  - dashboard response adapter pinned to data.snapshot with visible contract-drift failure'
Write-Host '  - integrated Users, Devices/Sessions, and Circles UI'
Write-Host '  - centralized admin-console CSS/JavaScript runtime'
Write-Host '  - consolidation architecture test'
Write-Host '  - original Foundation /admin/login + /admin/mfa retained as the only sign-in flow'
Write-Host '  - canonical session gate + Foundation browser-session compatibility (no network sniffing)'
Write-Host '  - historical M1 Foundation UI regression aligned to the canonical shell'
Write-Host ''
Write-Host 'Next run:'
Write-Host '  .\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1'
Write-Host 'Optional full regression after the targeted gate:'
Write-Host '  .\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1 -FullRegression'
Write-Host ''
Write-Host 'Rollback if needed:'
Write-Host "  .\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath '$backupRoot'"

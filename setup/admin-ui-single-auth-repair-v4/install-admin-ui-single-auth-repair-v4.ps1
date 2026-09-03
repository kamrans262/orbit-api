[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Section([string]$Title) {
    Write-Host ''
    Write-Host "== $Title ==" -ForegroundColor Cyan
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    [System.IO.File]::WriteAllText($Path, $Content, [System.Text.UTF8Encoding]::new($false))
}

$projectRoot = (Get-Location).Path
$artisan = Join-Path $projectRoot 'artisan'
if (-not (Test-Path $artisan)) {
    throw "Run this installer from the Orbit Laravel project root. artisan was not found in: $projectRoot"
}

$clientPath = Join-Path $projectRoot 'resources\js\admin-console\admin-api-client.js'
$canonicalSessionPath = Join-Path $projectRoot 'resources\js\admin-console\auth-session.js'
$canonicalClientPath = Join-Path $projectRoot 'resources\js\admin-console\api-client.js'
$generatedContractPath = Join-Path $projectRoot 'resources\js\admin-console\admin-auth.generated.js'
$verifier = Join-Path $PSScriptRoot 'verify-single-auth-v4.mjs'

foreach ($required in @($clientPath, $canonicalSessionPath, $canonicalClientPath, $generatedContractPath, $verifier)) {
    if (-not (Test-Path $required)) {
        throw "Required Orbit file not found: $required"
    }
}

$timestamp = Get-Date -Format 'yyyyMMddHHmmss'
$backupRoot = Join-Path $projectRoot "storage\app\orbit-admin-m4-m5-single-auth-backups\$timestamp"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null

$backupMap = @(
    @{ Source = $clientPath; Name = 'admin-api-client.js' },
    @{ Source = $generatedContractPath; Name = 'admin-auth.generated.js' }
)
foreach ($item in $backupMap) {
    Copy-Item $item.Source (Join-Path $backupRoot $item.Name) -Force
}

$manifest = [ordered]@{
    version = 'v4'
    created_at = (Get-Date).ToString('o')
    project_root = $projectRoot
    files = @(
        'resources/js/admin-console/admin-api-client.js',
        'resources/js/admin-console/admin-auth.generated.js'
    )
}
Write-Utf8NoBom (Join-Path $backupRoot 'manifest.json') ($manifest | ConvertTo-Json -Depth 4)

Write-Section 'Repair M4/M5 compatibility transport'
$source = Get-Content $clientPath -Raw
$canonicalImport = "import {adminAccessToken} from './auth-session.js';"

if (-not $source.Contains($canonicalImport)) {
    $source = $canonicalImport + [Environment]::NewLine + $source
}

if (-not $source.Contains('const token = adminAccessToken();')) {
    $pattern = 'async\s+function\s+execute\(route,\s*request,\s*token\s*=\s*null\)\s*\{\s*const\s+headers\s*=\s*baseHeaders\(\);'
    $match = [regex]::Match($source, $pattern, [System.Text.RegularExpressions.RegexOptions]::Singleline)
    if (-not $match.Success) {
        throw 'Safe patch aborted: the expected execute(route, request, token = null) signature was not found. No guessed source rewrite was attempted.'
    }

    $replacement = @'
async function execute(route, request, _legacyToken = null) {
    // M4/M5 must use the exact same administrator credential source as the
    // canonical Foundation shell. Any token inferred by legacy generated
    // storage metadata is intentionally ignored here.
    const token = adminAccessToken();
    const headers = baseHeaders();
'@
    $source = [regex]::Replace($source, $pattern, $replacement, [System.Text.RegularExpressions.RegexOptions]::Singleline)
}

Write-Utf8NoBom $clientPath $source

Write-Section 'Remove false browser-storage credential metadata'
$contract = @'
// Generated compatibility metadata for M4/M5. Do not hand edit.
// Authentication is owned exclusively by auth-session.js + api-client.js.
export const adminAuthContract = {"strategy":"canonical-auth-session-module","storageCandidates":[],"sourceRoots":["resources/js/admin-console/auth-session.js","resources/js/admin-console/api-client.js"],"graphFiles":["resources/js/admin-console/auth-session.js","resources/js/admin-console/api-client.js","resources/js/admin-console/shell.js"],"evidenceFiles":["resources/js/admin-console/auth-session.js","resources/js/admin-console/api-client.js"],"signals":{"sendsBearer":true,"cookieCredentials":true,"adminApi":true,"usesFetch":true}};
'@
Write-Utf8NoBom $generatedContractPath $contract

Write-Section 'Static transport verification'
& node $verifier $projectRoot
if ($LASTEXITCODE -ne 0) { throw "Single-auth verifier failed with exit code $LASTEXITCODE." }

foreach ($js in @($clientPath, $generatedContractPath, $canonicalSessionPath, $canonicalClientPath)) {
    & node --check $js
    if ($LASTEXITCODE -ne 0) { throw "node --check failed for $js" }
}

Write-Section 'Clear Laravel caches'
& php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'php artisan optimize:clear failed.' }

Write-Section 'Targeted UI regression'
$tests = @(
    'tests\Feature\AdminUi\AdminRuntimeIntegrationContractTest.php',
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
    'tests\Feature\AdminUi\AdminModerationRenderingSmokeTest.php',
    'tests\Feature\AdminUi\AdminModerationReportsUiTest.php',
    'tests\Feature\AdminUi\AdminSupportRenderingSmokeTest.php',
    'tests\Feature\AdminUi\AdminSupportManagementUiTest.php'
)
foreach ($test in $tests) {
    if (Test-Path (Join-Path $projectRoot $test)) {
        & php artisan test $test
        if ($LASTEXITCODE -ne 0) { throw "Targeted test failed: $test" }
    }
}

Write-Section 'Frontend build'
if (Test-Path (Join-Path $projectRoot 'package.json')) {
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build failed.' }
}

Write-Host ''
Write-Host 'Orbit M4 + M5 single-auth runtime repair installed.' -ForegroundColor Green
Write-Host "Backup: $backupRoot"
Write-Host 'No migrations or direct database mutations were performed.'
Write-Host 'Next: sign out once, sign in again through /admin/login + MFA, hard-refresh, then open Moderation & Reports and Support.'
Write-Host 'Optional full regression:'
Write-Host '  .\setup\admin-ui-single-auth-repair-v4\verify-admin-ui-single-auth-repair-v4.ps1 -FullRegression'

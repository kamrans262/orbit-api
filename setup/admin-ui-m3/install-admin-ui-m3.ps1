$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = (Get-Location).Path
$setupRoot = $PSScriptRoot
$payloadRoot = Join-Path $setupRoot 'payload'
$utf8NoBom = [System.Text.UTF8Encoding]::new($false)

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))) {
    throw 'Run this installer from the Orbit Laravel project root (the directory containing artisan).'
}

if (-not (Test-Path -LiteralPath $payloadRoot)) {
    throw "Milestone 3 payload not found: $payloadRoot"
}

# M3 intentionally extends the exact canonical M1+M2 v8 shell. Hard-fail on a
# mismatched base instead of silently creating another split or duplicate UI.
$expectedV8 = @{
    'resources\css\admin-console.css' = 'cb2c2e808c1e31ae730544a252a036107a987e4d2557b1958ff0c95c51d14b9a'
    'resources\js\admin-console\index.js' = 'b5be03287370400f30d5375b1cfa0e091c5311fc498b6e594923edcc663d887a'
    'resources\js\admin-console\shell.js' = '29a02cda2ac607e28d92da50eae0d33c7fb5aa8f46e21251208a2cd8636000b2'
    'resources\js\admin-console\ui.js' = '41e14b839c8dbc29247645ca7620e8e40ad8e20ed27206c023761afae19b3ee4'
    'resources\views\admin\partials\sidebar.blade.php' = '096e2eef78584f0ce167a00452cfc6d9db987863d835d26d62a13bd3e64c7646'
    'routes\admin_console.php' = '743d8bdff14a37870c964ef414bbef16960525bc249306ae853920d8a14b37d1'
    'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php' = 'a77a2fc78a45853b56c06b8a1840a5d8c955d2f536258f7d024e0e26b0fd777d'
}

foreach ($entry in $expectedV8.GetEnumerator()) {
    $path = Join-Path $projectRoot $entry.Key
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Canonical M1+M2 v8 base file is missing: $($entry.Key). Nothing was changed."
    }
    $actual = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $entry.Value) {
        throw "Canonical M1+M2 v8 base mismatch at $($entry.Key). Expected $($entry.Value), got $actual. Nothing was changed. Reinstall/verify v8 before M3 rather than layering over unknown UI code."
    }
}

$layoutPath = Join-Path $projectRoot 'resources\views\admin\layouts\app.blade.php'
if (-not (Test-Path -LiteralPath $layoutPath)) { throw 'Canonical admin layout is missing. Nothing was changed.' }
$layoutText = [System.IO.File]::ReadAllText($layoutPath)
if (-not $layoutText.Contains('data-orbit-canonical-shell="v1"') -or -not $layoutText.Contains('data-orbit-auth-owner="foundation"')) {
    throw 'The canonical Foundation-owned v1 admin shell markers are missing. Nothing was changed.'
}

# Preflight the route resolver itself, then inspect the real backend before touching UI files.
# The self-test includes the exact current Orbit SOS route shape and guards against
# accidentally treating the generic Controller class suffix as a /controls endpoint.
& node (Join-Path $setupRoot 'tests\sos-route-generator.mjs')
if ($LASTEXITCODE -ne 0) { throw 'M3 SOS route-contract resolver self-test failed. Nothing was changed.' }

# Generate a contract to a temporary path from the real Laravel route inventory.
# Operational closure is only bound to classification/controls when project source
# proves that endpoint owns operational_status + resolution + reason semantics.
$tempContract = Join-Path ([System.IO.Path]::GetTempPath()) ('orbit-sos-contract-' + [guid]::NewGuid().ToString('N') + '.js')
try {
    & (Join-Path $setupRoot 'generate-sos-contract.ps1') -OutputPath $tempContract
    if ($LASTEXITCODE -ne 0) { throw 'Admin SOS route-contract preflight failed.' }

    $backupStorageRoot = Join-Path $projectRoot 'storage\app\orbit-admin-ui-m3-backups'
    New-Item -ItemType Directory -Path $backupStorageRoot -Force | Out-Null
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $backupRoot = Join-Path $backupStorageRoot $stamp
    $backupFiles = Join-Path $backupRoot 'files'
    New-Item -ItemType Directory -Path $backupFiles -Force | Out-Null

    function Backup-Path {
        param([Parameter(Mandatory = $true)][string]$RelativePath)
        $source = Join-Path $projectRoot $RelativePath
        if (-not (Test-Path -LiteralPath $source)) { return }
        $destination = Join-Path $backupFiles $RelativePath
        New-Item -ItemType Directory -Path (Split-Path -Path $destination -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $source -Destination $destination -Recurse -Force
    }

    $backupPaths = @(
        'resources\css\admin-console.css',
        'resources\js\admin-console\index.js',
        'resources\js\admin-console\shell.js',
        'resources\js\admin-console\ui.js',
        'resources\js\admin-console\sos.js',
        'resources\js\admin-console\sos-contract.js',
        'resources\views\admin\partials\sidebar.blade.php',
        'resources\views\admin\operations\sos',
        'routes\admin_console.php',
        'tests\Feature\AdminUi\AdminConsoleConsolidationTest.php',
        'tests\Feature\AdminUi\AdminSosCommandCenterUiTest.php'
    )
    foreach ($relativePath in $backupPaths) { Backup-Path -RelativePath $relativePath }

    $backupInfo = @(
        'Orbit Admin UI Milestone 3 Safety / SOS backup',
        "Created: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss K')",
        "Project root: $projectRoot",
        'Base contract: canonical M1+M2 v8',
        'No database tables, migrations, records, backend controllers, or backend services are modified.'
    ) -join "`n"
    [System.IO.File]::WriteAllText((Join-Path $backupRoot 'BACKUP_INFO.txt'), ($backupInfo + "`n"), $utf8NoBom)
    [System.IO.File]::WriteAllText((Join-Path $setupRoot '.last-backup.txt'), ($backupRoot + "`n"), $utf8NoBom)

    Get-ChildItem -LiteralPath $payloadRoot -File -Recurse -Force | ForEach-Object {
        $relativePath = $_.FullName.Substring($payloadRoot.Length) -replace '^[\\/]+', ''
        $destination = Join-Path $projectRoot $relativePath
        New-Item -ItemType Directory -Path (Split-Path -Path $destination -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
    }

    # Replace the packaged expected contract with the contract generated from this
    # checkout's real backend route inventory.
    Copy-Item -LiteralPath $tempContract -Destination (Join-Path $projectRoot 'resources\js\admin-console\sos-contract.js') -Force

    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw 'M3 files were installed, but Laravel cache clearing failed. Rollback is available.' }

    Write-Host ''
    Write-Host 'Orbit Admin UI Milestone 3 Safety / SOS installed into the canonical M1+M2 shell.'
    Write-Host "Backup: $backupRoot"
    Write-Host ''
    Write-Host 'Installed:'
    Write-Host '  - one real Safety / SOS sidebar destination; no second shell or login flow'
    Write-Host '  - active command center + incident history with server-side filters/pagination'
    Write-Host '  - incident detail, responder/escalation/delivery/signal-health views'
    Write-Host '  - assignment, classification, private notes and operational closure'
    Write-Host '  - reason-coded sensitive location / encrypted recording reference reveals'
    Write-Host '  - sensitive access history and authorized privacy-safe export'
    Write-Host '  - adaptive background refresh while visible; no fake realtime status'
    Write-Host '  - actual backend route contract generated from php artisan route:list --json'
    Write-Host ''
    Write-Host 'No migrations or database writes were performed by this installer.'
    Write-Host 'Next run:'
    Write-Host '  .\setup\admin-ui-m3\verify-admin-ui-m3.ps1'
    Write-Host 'Optional full release regression after targeted verification:'
    Write-Host '  .\setup\admin-ui-m3\verify-admin-ui-m3.ps1 -FullRegression'
    Write-Host ''
    Write-Host 'Rollback if needed:'
    Write-Host "  .\setup\admin-ui-m3\rollback-admin-ui-m3.ps1 -BackupPath '$backupRoot'"
} finally {
    if (Test-Path -LiteralPath $tempContract) { Remove-Item -LiteralPath $tempContract -Force -ErrorAction SilentlyContinue }
}

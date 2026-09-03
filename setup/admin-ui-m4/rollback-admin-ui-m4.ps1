param([Parameter(Mandatory=$true)][string]$BackupPath)
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$backup=(Resolve-Path $BackupPath).Path
$manifest=Get-Content (Join-Path $backup 'manifest.json') -Raw | ConvertFrom-Json
foreach($rel in $manifest){$src=Join-Path $backup $rel;$dst=Join-Path $root $rel;Copy-Item $src $dst -Force}
@('routes\admin_ui_m4.php','resources\css\admin-console-m4.css','resources\js\admin-console\moderation-m4.js','resources\js\admin-console\moderation-routes.generated.js','resources\views\admin\console\operations\moderation','tests\Feature\AdminUi\AdminModerationReportsUiTest.php') | ForEach-Object { $p=Join-Path $root $_; if(Test-Path $p){Remove-Item $p -Recurse -Force} }
Set-Location $root
php artisan route:clear | Out-Host
php artisan view:clear | Out-Host
Write-Host 'Orbit Admin UI M4 rollback completed.' -ForegroundColor Green

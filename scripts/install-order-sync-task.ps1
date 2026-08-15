$ErrorActionPreference = 'Stop'

$TaskName = 'SMM Panel - Marketerum Order Sync'
$Php = 'C:\xampp\php\php.exe'
$Project = 'C:\xampp\htdocs\smmpanel'
$Worker = Join-Path $Project 'bin\sync-orders.php'
$Log = Join-Path $Project 'storage\logs\order-sync-worker.log'

if (-not (Test-Path $Php)) { throw "PHP executable not found: $Php" }
if (-not (Test-Path $Worker)) { throw "Worker not found: $Worker" }

$LogDirectory = Split-Path $Log -Parent
New-Item -ItemType Directory -Force -Path $LogDirectory | Out-Null

$Action = New-ScheduledTaskAction `
    -Execute $Php `
    -Argument "`"$Worker`" 100 >> `"$Log`" 2>&1" `
    -WorkingDirectory $Project

$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 2) -RepetitionDuration (New-TimeSpan -Days 3650)

$Settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) `
    -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -Description 'Synchronizes active Marketerum SMM orders and retries failed reconciliations.' `
    -RunLevel Highest `
    -Force | Out-Null

Start-ScheduledTask -TaskName $TaskName

Write-Host "Task installed and started: $TaskName" -ForegroundColor Green
Write-Host "Worker: $Worker"
Write-Host "Log: $Log"
Write-Host "Schedule: every 2 minutes"

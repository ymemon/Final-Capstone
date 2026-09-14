# Registers the client-mail watcher (every 15 min) and fires one run immediately.
# Safe to re-run: -Force replaces the existing task rather than duplicating it.

$ErrorActionPreference = "Stop"

$bat = "C:\Users\yasir\Documents\Final-Capstone\client-watch\run_client_watch.bat"

if (-not (Test-Path $bat)) {
    Write-Host "ERROR: $bat not found - aborting." -ForegroundColor Red
    exit 1
}

$action = New-ScheduledTaskAction -Execute $bat

# NOT [TimeSpan]::MaxValue - it serialises to P99999999DT23H59M59S, which this
# Task Scheduler rejects ("value which is incorrectly formatted or out of
# range"). 3650 days is the value the existing "AZWebCorp Ticket Mailbox Sync"
# task ended up with and is demonstrably accepted on this machine.
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 15) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask -TaskName "AZWebCorp Client Watch" `
    -Action $action -Trigger $trigger -Settings $settings `
    -Description "Watches info@azwebcorp.com. Lane A: known-client requests investigated and staged for approval, never deployed. Lane B: SEO audit alerts (Semrush/Ahrefs/Search Console) auto-fixed within the allowlist in prompt.txt, backed up and logged to autofix-log.md." `
    -Force | Out-Null

Write-Host "Registered: AZWebCorp Client Watch (every 15 min)" -ForegroundColor Green

Start-ScheduledTask -TaskName "AZWebCorp Client Watch"
Write-Host "First run started." -ForegroundColor Green
Write-Host ""

Get-ScheduledTaskInfo -TaskName "AZWebCorp Client Watch" |
    Select-Object LastRunTime, LastTaskResult, NextRunTime | Format-List

Write-Host "Give it 3-5 minutes (first run scans a 2-day backlog), then tell Claude to check the log."

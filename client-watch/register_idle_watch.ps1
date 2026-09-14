# Registers the real-time IMAP IDLE mail watcher so it survives logout,
# reboot and the end of any Claude session.
#
# Unlike the hourly "AZWebCorp Client Watch" task, this one is a LONG-RUNNING
# process, not a periodic job. So:
#   - it starts at logon and is also kicked hourly as a self-heal, because
#     Start-ScheduledTask on an already-running task is a no-op, which makes
#     the repeat harmless when it is alive and a restart when it is not
#   - there is no ExecutionTimeLimit; the default would kill it after 72 hours
#   - MultipleInstances is IgnoreNew so a self-heal tick can never start a
#     second copy holding a second IMAP connection
#
# Safe to re-run: -Force replaces rather than duplicating.

$ErrorActionPreference = "Stop"

$dir = "C:\Users\yasir\Documents\Final-Capstone\client-watch"
$bat = Join-Path $dir "run_idle_watch.bat"

if (-not (Test-Path $bat)) {
    Write-Host "ERROR: $bat not found - aborting." -ForegroundColor Red
    exit 1
}

$action = New-ScheduledTaskAction -Execute $bat -WorkingDirectory $dir

# -AtLogOn MUST be scoped to a specific user. Without -User it registers as an
# "any user logs on" trigger, which is a machine-wide change and needs an
# elevated shell - it fails with "Access is denied" (HRESULT 0x80070005).
# Scoping it to the current account registers fine unelevated.
$me = "$env:USERDOMAIN\$env:USERNAME"

$atLogon = New-ScheduledTaskTrigger -AtLogOn -User $me
$heal    = New-ScheduledTaskTrigger -Once -At (Get-Date) `
              -RepetitionInterval (New-TimeSpan -Hours 1) `
              -RepetitionDuration (New-TimeSpan -Days 3650)

# ExecutionTimeLimit 0 = run indefinitely. The default 72h would silently kill
# the watcher after three days, which is exactly the failure that would go
# unnoticed.
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd `
              -ExecutionTimeLimit ([TimeSpan]::Zero) `
              -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 5) `
              -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName "AZWebCorp Mail IDLE Watch" `
    -Action $action -Trigger @($atLogon, $heal) -Settings $settings `
    -User $me -RunLevel Limited `
    -Description "Holds an IMAP IDLE connection to info@azwebcorp.com so new mail is seen within seconds instead of up to an hour. Read-only. Fires the ticket sync when mail from a known client or an SEO audit source arrives. Logs to client-watch/idle_watch.log and idle_events.jsonl." `
    -Force | Out-Null

Write-Host "Registered: AZWebCorp Mail IDLE Watch" -ForegroundColor Green

Start-ScheduledTask -TaskName "AZWebCorp Mail IDLE Watch"
Write-Host "Started." -ForegroundColor Green
Write-Host ""
Get-ScheduledTaskInfo -TaskName "AZWebCorp Mail IDLE Watch" |
    Select-Object LastRunTime, LastTaskResult, NumberOfMissedRuns | Format-List

Write-Host "Watch it work:  Get-Content '$dir\idle_watch.log' -Tail 20 -Wait"

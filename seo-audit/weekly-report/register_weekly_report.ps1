# Registers the Monday weekly SEO report.
#
# -AtLogOn style triggers need a named user or they register machine-wide and
# fail with "Access is denied" unelevated - the same trap hit on 2026-08-27
# with the IDLE watcher. A weekly trigger does not need -User, but the task
# principal does, so it is set explicitly.
$ErrorActionPreference = "Stop"

$dir = "C:\Users\yasir\Documents\Final-Capstone\seo-audit\weekly-report"
$bat = Join-Path $dir "run_weekly_report.bat"
if (-not (Test-Path $bat)) { Write-Host "ERROR: $bat not found" -ForegroundColor Red; exit 1 }

$me = "$env:USERDOMAIN\$env:USERNAME"
$action  = New-ScheduledTaskAction -Execute $bat -WorkingDirectory $dir

# 08:00 every Monday. StartWhenAvailable catches the case where the machine
# was off at 08:00 - the report still goes out when it next wakes, rather than
# silently skipping the week.
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday -At 8am

$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd `
              -ExecutionTimeLimit (New-TimeSpan -Hours 1) `
              -MultipleInstances IgnoreNew `
              -RestartCount 2 -RestartInterval (New-TimeSpan -Minutes 10)

Register-ScheduledTask -TaskName "AZWebCorp Weekly SEO Report" `
    -Action $action -Trigger $trigger -Settings $settings `
    -User $me -RunLevel Limited `
    -Description "Every Monday 08:00: pulls Search Console figures for every configured site, emails each client their own results (good or bad), copies ceo@azwebcorp.com, and appends to weekly-report/sent-log.md. Sites without GSC access produce an internal notice instead of an empty client email." `
    -Force | Out-Null

Write-Host "Registered: AZWebCorp Weekly SEO Report (Mondays 08:00)" -ForegroundColor Green
Get-ScheduledTaskInfo -TaskName "AZWebCorp Weekly SEO Report" |
    Select-Object LastRunTime, LastTaskResult, NextRunTime | Format-List

<#
    Let the AZ Web Corp watchers run while the laptop is on battery.

    WHY THIS IS NEEDED
    All four scheduled tasks were created with Task Scheduler's default power
    conditions:

        DisallowStartIfOnBatteries = True
        StopIfGoingOnBatteries     = True

    So the moment the laptop comes off AC, they stop firing. On 2026-09-09 that
    is exactly what happened: Yasir unplugged at around 08:39 and the 15-minute
    mail check, the ticket sync and the hourly IDLE watch all silently stopped.
    Task Scheduler queued the triggers rather than reporting an error, so from
    the outside it looked like everything was "Ready" while nothing ran for an
    hour.

    These are background jobs that take seconds and make a handful of network
    calls. The battery cost is negligible; missing a client email for hours is
    not.

    RUN THIS AS ADMINISTRATOR - the tasks were registered elevated, so a normal
    session gets "Access is denied".

        Right-click this file > Run with PowerShell (as Administrator)
      or, in an elevated PowerShell:
        powershell -ExecutionPolicy Bypass -File "<path to this file>"
#>

$tasks = @(
    'AZWebCorp Client Watch',
    'AZWebCorp Ticket Mailbox Sync',
    'AZWebCorp Mail IDLE Watch',
    'AZWebCorp Weekly SEO Report',
    'AZWebCorp Approval Daemon'
)

$elevated = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent()
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $elevated) {
    Write-Host "This needs to run as Administrator. Re-launching..." -ForegroundColor Yellow
    Start-Process powershell.exe -Verb RunAs -ArgumentList `
        "-ExecutionPolicy Bypass -NoExit -File `"$PSCommandPath`""
    return
}

foreach ($name in $tasks) {
    try {
        $t = Get-ScheduledTask -TaskName $name -ErrorAction Stop
        $t.Settings.DisallowStartIfOnBatteries = $false
        $t.Settings.StopIfGoingOnBatteries     = $false
        Set-ScheduledTask -TaskName $name -Settings $t.Settings -ErrorAction Stop | Out-Null

        $c = Get-ScheduledTask -TaskName $name
        '{0,-32} blocked-on-battery={1,-5} stops-on-battery={2}' -f `
            $name, $c.Settings.DisallowStartIfOnBatteries, $c.Settings.StopIfGoingOnBatteries |
            Write-Host -ForegroundColor Green
    }
    catch {
        '{0,-32} SKIPPED: {1}' -f $name, $_.Exception.Message | Write-Host -ForegroundColor DarkYellow
    }
}

# A task wedged in "Queued" from the battery block stays queued. Clearing and
# restarting it gets the schedule moving again without waiting for the next tick.
foreach ($name in $tasks) {
    $t = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    if ($t -and $t.State -eq 'Queued') {
        Stop-ScheduledTask  -TaskName $name -ErrorAction SilentlyContinue
        Start-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
        Write-Host ("{0,-32} was Queued - cleared and restarted" -f $name) -ForegroundColor Cyan
    }
}

Write-Host ''
Write-Host 'Done. Current state:' -ForegroundColor White
Get-ScheduledTask -TaskName $tasks -ErrorAction SilentlyContinue |
    Select-Object TaskName, State |
    Format-Table -AutoSize

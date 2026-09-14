@echo off
REM Drains the Rebound Body Studio booking system's mail queue, over HTTP.
REM
REM This exists because the hosting (GoDaddy Managed WordPress) has no shell
REM crontab and no control-panel cron for this plan - confirmed 2026-09-10.
REM Without this, queued mail (booking confirmations, admin login links,
REM reminders) just sits until someone happens to SSH in and run
REM drain-mail.php by hand, which is exactly what left Randi's dashboard
REM login link unsent for hours. Runs every 5 minutes via the "Rebound Mail
REM Drain" scheduled task - same pattern as the AZWebCorp mail watcher.
curl --request POST --silent --show-error --fail --max-time 20 --header "Cache-Control: no-cache" "https://azwebcorp.com/preview/rebound/booking/cron/drain-http.php?token=Ex1hKgN8srQz2NE-ZbscT1CMlQOq7vfhH2fHLSsIhho&run=%RANDOM%%RANDOM%%RANDOM%" >> "C:\Users\yasir\Documents\Final-Capstone\rebound-body-studio\booking\drain_mail_http.log" 2>&1
echo. >> "C:\Users\yasir\Documents\Final-Capstone\rebound-body-studio\booking\drain_mail_http.log"

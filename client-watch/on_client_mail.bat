@echo off
REM Fired by idle_watch.py the moment a client/audit email actually arrives -
REM not on a timer. This replaces the old fixed 15-minute poll as the primary
REM path for the client-mail briefing: that poll depended on Windows Task
REM Scheduler firing reliably every cycle, and on 2026-09-10 it silently
REM stopped firing for hours with nothing in the event log to explain why.
REM The IDLE watcher is a single long-running process with its own retry/
REM backoff loop and a Task-Scheduler restart policy behind it, so this path
REM does not depend on a new process being spawned successfully every 15
REM minutes - it depends on one process staying up, which is far more
REM reliable and is already proven to self-heal (see idle_watch.log).
cd /d "C:\Users\yasir\Documents\Final-Capstone\client-watch"

echo ==== real-time trigger fired %date% %time% ==== >> watch_log.txt

call "C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync\run_ticket_sync.bat" >> watch_log.txt 2>&1

type prompt.txt | claude -p --dangerously-skip-permissions --output-format text >> watch_log.txt 2>&1

echo ==== real-time trigger finished %date% %time% ==== >> watch_log.txt
echo. >> watch_log.txt

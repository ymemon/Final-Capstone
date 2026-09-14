@echo off
cd /d "C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync"

echo ==== run started %date% %time% ==== >> sync_log.txt

type prompt.txt | claude -p --dangerously-skip-permissions --output-format text >> sync_log.txt 2>&1

echo ==== run finished %date% %time% ==== >> sync_log.txt
echo. >> sync_log.txt

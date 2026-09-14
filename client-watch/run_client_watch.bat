@echo off
REM Approval-send only, on a short fixed interval. The full client-mail
REM analysis (the heavy "claude -p" pass) moved to on_client_mail.bat, fired
REM in real time by idle_watch.py the moment a client email actually arrives -
REM see that file's header for why. This task now exists only so that
REM clicking Approve on a queued draft does not have to wait for the next
REM piece of client mail to show up before it gets sent; it is deliberately
REM light (no Claude invocation) so it has nothing heavy left to hang on.
cd /d "C:\Users\yasir\Documents\Final-Capstone\client-watch"

echo ==== approval-poll run %date% %time% ==== >> watch_log.txt
python "C:\Users\yasir\Documents\Final-Capstone\azwebcorp-email\approval\approval.py" --poll >> watch_log.txt 2>&1
echo ==== approval-poll finished %date% %time% ==== >> watch_log.txt
echo. >> watch_log.txt

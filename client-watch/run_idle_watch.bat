@echo off
REM Long-running IMAP IDLE mail watcher. Started by the "AZWebCorp Mail IDLE
REM Watch" scheduled task at logon, and kicked hourly as a self-heal (a no-op
REM while it is already running). Read-only on the mailbox.
cd /d "C:\Users\yasir\Documents\Final-Capstone\client-watch"
set PYTHONIOENCODING=utf-8
REM Rebound's GoDaddy host cannot deliver PHP mail or open outbound SMTP.
REM Keep its authenticated delivery worker alive under this proven S4U task.
start "" /b "C:\Users\yasir\AppData\Local\Python\pythoncore-3.14-64\python.exe" "C:\Users\yasir\Documents\Final-Capstone\rebound-body-studio\booking\rebound_mail_daemon.py"
python idle_watch.py --trigger "C:\Users\yasir\Documents\Final-Capstone\client-watch\on_client_mail.bat"

@echo off
REM Weekly SEO report - fires every Monday via "AZWebCorp Weekly SEO Report".
REM Sends each client their own Search Console figures, copies ceo@azwebcorp.com,
REM and appends to sent-log.md so there is always a record.
cd /d "C:\Users\yasir\Documents\Final-Capstone\seo-audit\weekly-report"
set PYTHONIOENCODING=utf-8
python weekly_seo_report.py --send >> run_output.log 2>&1

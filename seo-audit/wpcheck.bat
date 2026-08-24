@echo off
REM ===================================================================
REM  WordPress REST API auth diagnostic for azwebcorp.com
REM  Uses curl, built into Windows 10/11. No Python required.
REM
REM  Just double-click this file. The window stays open at the end,
REM  and everything is saved to wpcheck-bat-output.txt next to it.
REM ===================================================================

setlocal
cd /d "%~dp0"

set SITE=https://azwebcorp.com
set WPUSER=admin
set WPPASS=P8kz OExK QxNJ WoBB P7A4 tTrm
set OUT=wpcheck-bat-output.txt

echo ==================================================================== > "%OUT%"
echo Site:     %SITE%                                                    >> "%OUT%"
echo Username: %WPUSER%                                                  >> "%OUT%"
echo ==================================================================== >> "%OUT%"
echo.                                                                    >> "%OUT%"

echo Running checks, please wait...
echo.

echo [1] Is the REST API reachable at all? (no auth)                     >> "%OUT%"
echo -------------------------------------------------------------------- >> "%OUT%"
curl -s -w "HTTP_STATUS:%%{http_code}" "%SITE%/wp-json/" >> "%OUT%" 2>&1
echo.                                                                    >> "%OUT%"
echo.                                                                    >> "%OUT%"

echo [2] Authenticated request - THE KEY TEST                            >> "%OUT%"
echo -------------------------------------------------------------------- >> "%OUT%"
curl -s -w "HTTP_STATUS:%%{http_code}" -u "%WPUSER%:%WPPASS%" "%SITE%/wp-json/wp/v2/users/me" >> "%OUT%" 2>&1
echo.                                                                    >> "%OUT%"
echo.                                                                    >> "%OUT%"

echo [3] Existing draft pages (only works if [2] succeeded)              >> "%OUT%"
echo -------------------------------------------------------------------- >> "%OUT%"
curl -s -w "HTTP_STATUS:%%{http_code}" -u "%WPUSER%:%WPPASS%" "%SITE%/wp-json/wp/v2/pages?status=draft&per_page=50&_fields=id,slug,status" >> "%OUT%" 2>&1
echo.                                                                    >> "%OUT%"
echo.                                                                    >> "%OUT%"

echo [4] Published pages (sanity check, no auth needed)                  >> "%OUT%"
echo -------------------------------------------------------------------- >> "%OUT%"
curl -s -w "HTTP_STATUS:%%{http_code}" "%SITE%/wp-json/wp/v2/pages?per_page=50&_fields=id,slug" >> "%OUT%" 2>&1
echo.                                                                    >> "%OUT%"

echo.
echo ====================================================================
echo  DONE. Results saved to:
echo    %CD%\%OUT%
echo ====================================================================
echo.
echo  What to look for in section [2]:
echo.
echo    "rest_not_logged_in"  = host is STRIPPING the auth header
echo                            (common on GoDaddy - fixable in .htaccess)
echo    "incorrect_password"  = wrong app password
echo    "invalid_username"    = 'admin' is not the real username
echo    your user details     = AUTH WORKS
echo.
echo  Opening the results file now...
echo.

timeout /t 2 >nul
start notepad "%OUT%"

echo Press any key to close this window...
pause >nul

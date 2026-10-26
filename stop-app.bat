@echo off
setlocal
echo ==================================================
echo  Indonesia Defence System - Stopping...
echo ==================================================

:: Mematikan semua proses di port 8090, 787, 7788
echo Mematikan proses background (8090, 787, 7788, sync-gate)...
for %%P in (8090 787 7788) do (
  for /f "tokens=5" %%a in ('netstat -aon ^| findstr /R /C:":%%P .*LISTENING"') do (
    echo   - kill PID %%a di port %%P
    taskkill /PID %%a /T /F >nul 2>&1
  )
)

:: Hentikan daemon background loop sync-gate
wmic process where "commandline like '%%users:sync-gate%%'" call terminate >nul 2>&1

echo.
echo Selesai! Aplikasi sudah berhenti.
echo ==================================================
timeout /t 2 /nobreak >nul
exit /b 0

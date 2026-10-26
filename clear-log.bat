@echo off
:: ==================================================
:: FitQuarters Gym - Bersihkan Log Manual / Scheduler
:: ==================================================
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" artisan log:clear
) else (
    php artisan log:clear
)

@echo off
:: ==================================================
:: Indonesia Defence System - Hidden Launcher
:: Memanggil VBScript agar proses berjalan di background
:: tanpa memunculkan jendela CMD (windowless)
:: ==================================================

set "DIR=%~dp0"
echo Memulai Indonesia Defence System di background (Hidden)...
wscript //nologo "%DIR%start-hidden.vbs"

:: Exit batch ini langsung, sisanya VBS yang kerjakan
exit /b 0

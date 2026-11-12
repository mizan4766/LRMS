@echo off
title LARMS Server
cd /d "%~dp0backend"
if not exist artisan ( echo Run install.bat first. & pause & exit /b 1 )
echo Starting LARMS at http://127.0.0.1:8000/  ^(keep this window open; close it to stop^)
start "" cmd /c "timeout /t 4 >nul & start http://127.0.0.1:8000/"
start "LARMS scheduler" /min cmd /c "php artisan schedule:work"
php artisan serve --host=127.0.0.1 --port=8000
pause

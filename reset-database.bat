@echo off
cd /d "%~dp0backend"
echo WARNING: this DELETES all data in the LARMS database and recreates it.
set /p OK=Type YES to continue: 
if /i not "%OK%"=="YES" ( echo Cancelled. & pause & exit /b 0 )
php artisan migrate:fresh --force --seed
php artisan larms:check
pause

@echo off
cd /d "%~dp0backend"
php artisan optimize:clear >nul
php artisan larms:check
echo.
echo Last lines of the error log ^(if any^):
if exist storage\logs\laravel.log powershell -NoProfile -Command "Get-Content storage\logs\laravel.log -Tail 15"
pause

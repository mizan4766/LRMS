@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
title LARMS Installer
cd /d "%~dp0"
echo ==========================================================
echo   LARMS - Land Acquisition and Requisition Management
echo   One-time installer for Windows (XAMPP / PHP 8.2+)
echo ==========================================================
echo.

where php >nul 2>nul
if errorlevel 1 ( echo [ERROR] PHP was not found. Add your PHP folder ^(for example C:\xampp\php^) to the Windows PATH, then run this file again. & goto :fail )
where composer >nul 2>nul
if errorlevel 1 ( echo [ERROR] Composer was not found. Install it from https://getcomposer.org/Composer-Setup.exe then run this file again. & goto :fail )
php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);"
if errorlevel 1 ( echo [ERROR] PHP 8.2 or newer is required. & php -v & goto :fail )
php -v | findstr /i "PHP"

echo.
echo [1/8] Enabling the PHP extensions LARMS needs ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\enable-extensions.ps1"
set MISSING=
for %%E in (gd zip mbstring pdo_mysql openssl fileinfo curl intl) do (
  php -m | findstr /i /x "%%E" >nul
  if errorlevel 1 set MISSING=!MISSING! %%E
)
if not "!MISSING!"=="" (
  echo [ERROR] These PHP extensions are still missing:!MISSING!
  echo         Open the php.ini shown by "php --ini", remove the ";" before each line "extension=NAME", save, and run this file again.
  goto :fail
)
echo      PHP extensions OK.

echo.
echo [2/8] Database settings ^(press Enter to accept the default in brackets^)
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_NAME=larms
set DB_USER=root
set DB_PASS=
set /p DB_HOST=MySQL host [127.0.0.1]: 
set /p DB_PORT=MySQL port [3306]: 
set /p DB_NAME=Database name [larms]: 
set /p DB_USER=MySQL user [root]: 
set /p DB_PASS=MySQL password [empty]: 
set LARMS_ADMIN_PASSWORD=ChangeMe@123
php tools\create-db.php
if errorlevel 1 ( echo [ERROR] Start MySQL in the XAMPP Control Panel and check the user/password, then run this file again. & goto :fail )

if exist "backend\.larms_installed" goto :migrate

echo.
echo [3/8] Creating the Laravel application ^(downloads packages, takes a few minutes^) ...
if not exist "backend\artisan" (
  call composer create-project laravel/laravel backend --no-interaction
  if errorlevel 1 ( echo [ERROR] Could not create the Laravel project. Check your internet connection. & goto :fail )
)
cd backend

echo.
echo [4/8] Preparing the Laravel application ^(no extra packages are needed^) ...
if exist "database\migrations\0001_01_01_000000_create_users_table.php" del "database\migrations\0001_01_01_000000_create_users_table.php"

echo.
echo [5/8] Copying LARMS code and the ready-made Bangla user interface ...
xcopy "..\backend-overlay\*" . /E /Y /I /Q >nul
if errorlevel 1 ( echo [ERROR] Copy of backend-overlay failed. & goto :fail_in )
if not exist "public\app" mkdir "public\app"
xcopy "..\frontend-dist\*" "public\app\" /E /Y /I /Q >nul
if errorlevel 1 ( echo [ERROR] Copy of frontend-dist failed. & goto :fail_in )
if exist setup.sh del setup.sh
powershell -NoProfile -ExecutionPolicy Bypass -File "..\tools\set-timezone.ps1"
echo done> .larms_installed
cd ..

:migrate
cd backend
echo.
echo [6/8] Writing settings to backend\.env ...
powershell -NoProfile -ExecutionPolicy Bypass -File "..\tools\configure-env.ps1" -Path ".env"
php artisan key:generate --force >nul
php artisan config:clear >nul

echo.
echo [7/8] Creating tables and loading the legal settings ^(about 18 tables^) ...
php artisan migrate --force --seed
if errorlevel 1 (
  echo [ERROR] Migration failed. If it says a table already exists, run reset-database.bat once.
  goto :fail_in
)

echo.
set DEMO=Y
set /p DEMO=Load the demo application ^(already seeded^) and run the workflow self-test? [Y/n]: 
if /i not "!DEMO!"=="N" php artisan larms:simulate

echo.
echo [8/8] Final check ...
php artisan optimize:clear >nul
php artisan larms:check
cd ..

echo.
echo ==========================================================
echo   INSTALLATION FINISHED
echo   Start the system:  double-click  start.bat
echo   Open in browser:   http://127.0.0.1:8000/
echo   Admin login:       admin  /  ChangeMe@123
echo   Demo users:        see README ^(all use password Land1234^)
echo ==========================================================
pause
exit /b 0

:fail_in
cd ..
:fail
echo.
echo Installation stopped. Nothing is lost - fix the message above and run install.bat again.
pause
exit /b 1

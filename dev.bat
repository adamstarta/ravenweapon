@echo off
setlocal

set DOCKER="C:\Program Files\Docker\Docker\resources\bin\docker.exe"
set CONTAINER=ravenweapon-shop

if "%1"=="" goto help
if "%1"=="start" goto start
if "%1"=="stop" goto stop
if "%1"=="compile" goto compile
if "%1"=="cache" goto cache
if "%1"=="logs" goto logs
if "%1"=="shell" goto shell
if "%1"=="status" goto status
goto help

:start
echo Starting Shopware container...
cd /d "%~dp0"
%DOCKER% compose up -d
goto end

:stop
echo Stopping Shopware container...
cd /d "%~dp0"
%DOCKER% compose down
goto end

:compile
echo Compiling theme and clearing cache...
%DOCKER% exec %CONTAINER% bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
goto end

:cache
echo Clearing cache...
%DOCKER% exec %CONTAINER% bash -c "cd /var/www/html && bin/console cache:clear"
goto end

:logs
echo Showing container logs (Ctrl+C to exit)...
%DOCKER% logs -f %CONTAINER%
goto end

:shell
echo Opening shell in container...
%DOCKER% exec -it %CONTAINER% bash
goto end

:status
%DOCKER% ps --filter name=%CONTAINER%
goto end

:help
echo.
echo Raven Weapon Development Helper
echo ===============================
echo.
echo Usage: dev [command]
echo.
echo Commands:
echo   start    - Start the Shopware container
echo   stop     - Stop the Shopware container
echo   compile  - Compile theme and clear cache
echo   cache    - Clear cache only
echo   logs     - Show container logs
echo   shell    - Open bash shell in container
echo   status   - Show container status
echo.
echo URLs:
echo   Storefront: http://localhost
echo   Admin:      http://localhost/admin (admin / shopware)
echo   Adminer:    http://localhost:8888
echo.
goto end

:end
endlocal

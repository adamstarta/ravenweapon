@echo off
echo ============================================
echo   SNIGEL PRODUCT IMPORT FOR RAVEN WEAPON
echo ============================================
echo.

REM Check if PHP is available
php -v > nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP is not installed or not in PATH.
    echo Please install PHP and add it to your system PATH.
    pause
    exit /b 1
)

echo Step 1: Scraping products from Snigel B2B portal...
echo.
php "%~dp0snigel-scraper.php"

if errorlevel 1 (
    echo.
    echo ERROR: Scraping failed!
    pause
    exit /b 1
)

echo.
echo ============================================
echo.
echo Step 2: Do you want to import products into Shopware?
echo.
set /p import="Enter Y to import, N to skip: "

if /i "%import%"=="Y" (
    echo.
    echo Importing products into Shopware...
    echo.
    php "%~dp0shopware-import.php"
)

echo.
echo ============================================
echo   COMPLETE!
echo ============================================
echo.
echo Output files are in: %~dp0snigel-data\
echo.
pause

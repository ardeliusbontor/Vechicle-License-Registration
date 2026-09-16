@echo off
echo ========================================
echo   TUA License Plate System - File Copy
echo ========================================
echo.
echo Copying all files to XAMPP htdocs\CAR LICENSES...
xcopy "%~dp0" "C:\xampp\htdocs\CAR LICENSES" /E /I /Y /H /R
echo.
echo ========================================
echo   File Copy Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Make sure Apache and MySQL are running in XAMPP Control Panel
echo 2. Open browser and go to http://localhost/CAR%%20LICENSES/install.php
echo 3. After install, go to http://localhost/CAR%%20LICENSES/
echo.
pause

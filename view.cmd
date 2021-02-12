@echo off
setlocal
set root=%CD%
cd /d "%~dp0"
path %PATH%;c:\Compiler\php
set /a port=%RANDOM% + 33000
start firefox.exe http://localhost:%PORT%/
start "PHP Server" /B /MIN php -c . -S localhost:%port% -t "%root%" %~n0.php
REM pause >&2
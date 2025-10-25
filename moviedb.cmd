@echo off
setlocal
set root=%CD%
cd /d "%~dp0"
set "subpath=%~1"
if defined subpath set "subpath=%subpath:\=/%"
if defined subpath set "subpath=%subpath: =+%"
if defined subpath set "subpath=?path=%subpath%"

path %PATH%;c:\Compiler\php
if not defined port set /a port=%RANDOM% + 33000
if not defined batch start "" firefox.exe "http://localhost:%PORT%/%subpath%"
echo php -c . -S localhost:%port% -t "%root%" %~n0.php
if not defined batch (
echo [InternetShortcut]
echo url=http://localhost:%port%/
)>"%~dpn0.url"
start "PHP Server" /ABOVENORMAL /B /MIN php -c . -S localhost:%port% -t "%root%" %~n0.php
REM pause >&2

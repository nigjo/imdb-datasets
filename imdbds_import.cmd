@echo off
setlocal
cd /d "%~dp0"
path %PATH%;c:\Compiler\php

php -v
php -c . %~n0.php %*

pause >&2
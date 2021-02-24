@echo off
setlocal
set baseurl=https://datasets.imdbws.com

call :download name.basics.tsv.gz
call :download title.akas.tsv.gz
call :download title.basics.tsv.gz
call :download title.crew.tsv.gz
call :download title.principals.tsv.gz
call :download title.ratings.tsv.gz
echo --------------------------------------------------
set dlstatus
echo --------------------------------------------------

pause
goto :eof
:download
set "line=-----%~1---------------------------------------------"
echo %line:~0,50%
title download %1 - %~nx0
set FROM=%baseurl%/%1
set TO=data\%1
set dlstatus_%1=PENDING
powershell -C "$ProgressPreference = 'SilentlyContinue';Invoke-WebRequest -Uri %FROM% -OutFile %TO%;"
if errorlevel 1 (
set dlstatus_%1=ERROR-%ERRORLEVEL%
) else (
set dlstatus_%1=OK
)

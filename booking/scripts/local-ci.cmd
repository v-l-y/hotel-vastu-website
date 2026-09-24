@echo off
setlocal
cd /d "%~dp0.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0local-ci.ps1" %*
exit /b %ERRORLEVEL%

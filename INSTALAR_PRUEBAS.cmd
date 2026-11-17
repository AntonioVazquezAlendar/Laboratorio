@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALAR_PRUEBAS.ps1" %*
set "RESULTADO=%ERRORLEVEL%"
echo.
pause
exit /b %RESULTADO%

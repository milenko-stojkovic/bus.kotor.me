@echo off
setlocal EnableDelayedExpansion
REM Laravel artisan preko Laragon PHP-a — radi i kada je PowerShell ExecutionPolicy stroga (.ps1 se ne može pokrenuti).
set "ARTISAN=%~dp0artisan"
set "LARAGON_PHP_ROOT=C:\laragon\bin\php"
set "PHP_EXE="
if exist "%LARAGON_PHP_ROOT%" (
  for /f "delims=" %%i in ('dir /b /ad /o:-n "%LARAGON_PHP_ROOT%" 2^>nul') do (
    if exist "!LARAGON_PHP_ROOT!\%%i\php.exe" (
      set "PHP_EXE=!LARAGON_PHP_ROOT!\%%i\php.exe"
      goto :run
    )
  )
)
:run
if "!PHP_EXE!"=="" (
  where php >nul 2>&1 && (
    php "%ARTISAN%" %*
    exit /b %ERRORLEVEL%
  )
  echo ERROR: php.exe nije pronadjen. Proveri Laragon ^(C:\laragon\bin\php^) ili dodaj php u PATH.
  exit /b 1
)
"!PHP_EXE!" "%ARTISAN%" %*
exit /b %ERRORLEVEL%

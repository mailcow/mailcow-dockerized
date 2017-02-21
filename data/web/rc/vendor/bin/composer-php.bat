@echo off
setlocal enabledelayedexpansion
set BIN_DIR=%~dp0
set VENDOR_DIR=%BIN_DIR%\../
set DIRS=.
FOR /D %%V IN (%VENDOR_DIR%\*) DO (
    FOR /D %%P IN (%%V\*) DO (
        set DIRS=!DIRS!;%%~fP
    )
)
php.exe -d include_path=!DIRS! %*

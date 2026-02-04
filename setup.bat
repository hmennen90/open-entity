@echo off
REM OpenEntity Setup Script für Windows
REM Einfache Alternative zur PowerShell

echo.
echo OpenEntity Setup
echo ================
echo.

REM GPU prüfen
nvidia-smi >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo GPU:     NVIDIA erkannt
    nvidia-smi --query-gpu=name,memory.total --format=csv,noheader
    echo.
    set COMPOSE_CMD=docker compose -f docker-compose.yml -f docker-compose.gpu.yml
) else (
    echo GPU:     Keine NVIDIA GPU ^(CPU-Modus^)
    echo.
    set COMPOSE_CMD=docker compose
)

if "%1"=="start" goto start
if "%1"=="-s" goto start
if "%1"=="--start" goto start
goto info

:start
echo Starte Container...
echo ^> %COMPOSE_CMD% up -d
echo.
%COMPOSE_CMD% up -d
echo.
echo Container gestartet!
echo.
echo Frontend: http://localhost:8080
echo API:      http://localhost:8080/api/v1
goto end

:info
echo Zum Starten:
echo.
echo   setup.bat start
echo.
echo   oder manuell:
echo   %COMPOSE_CMD% up -d
goto end

:end
echo.

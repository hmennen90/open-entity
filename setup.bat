@echo off
REM OpenEntity Setup Script for Windows (Batch)
REM For full functionality, use setup.ps1 (PowerShell)
REM
REM Usage: setup.bat [command]
REM   install  - Install Ollama and pull models
REM   start    - Start Ollama and Docker
REM   stop     - Stop Docker containers
REM   status   - Show status

setlocal enabledelayedexpansion
cd /d "%~dp0"

echo.
echo OpenEntity Setup
echo ================
echo.

REM Check for NVIDIA GPU
set GPU_TYPE=cpu
nvidia-smi >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    set GPU_TYPE=nvidia
    echo Platform: Windows with NVIDIA GPU
    for /f "tokens=*" %%i in ('nvidia-smi --query-gpu^=name^,memory.total --format^=csv^,noheader 2^>nul') do echo          %%i
) else (
    echo Platform: Windows ^(CPU mode^)
)
echo.

if "%1"=="install" goto install
if "%1"=="start" goto start
if "%1"=="stop" goto stop
if "%1"=="status" goto status
if "%1"=="help" goto help
if "%1"=="-h" goto help
if "%1"=="--help" goto help
goto info

:install
echo Installing Ollama...
echo.

REM Check if Ollama is already installed
where ollama >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo Ollama is already installed
    goto start_ollama
)

REM Try winget first
where winget >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo Installing via winget...
    winget install Ollama.Ollama --accept-package-agreements --accept-source-agreements
    goto refresh_path
)

REM Download and install manually
echo Downloading Ollama installer...
powershell -Command "Invoke-WebRequest -Uri 'https://ollama.ai/download/OllamaSetup.exe' -OutFile '%TEMP%\OllamaSetup.exe'"
echo Running installer...
start /wait %TEMP%\OllamaSetup.exe
del %TEMP%\OllamaSetup.exe 2>nul

:refresh_path
REM Refresh PATH
for /f "tokens=2*" %%a in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path 2^>nul') do set "SYSPATH=%%b"
for /f "tokens=2*" %%a in ('reg query "HKCU\Environment" /v Path 2^>nul') do set "USERPATH=%%b"
set "PATH=%SYSPATH%;%USERPATH%"

:start_ollama
echo.
echo Starting Ollama...
start /b ollama serve >nul 2>&1
timeout /t 5 /nobreak >nul

echo Pulling models...
ollama pull qwen2.5:7b
ollama pull nomic-embed-text

echo.
echo Configuring .env...
call :configure_env

echo.
echo Installation complete!
echo Run: setup.bat start
goto end

:start
echo Starting services...
echo.

REM Check if Ollama is installed
where ollama >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Ollama is not installed. Run 'setup.bat install' first.
    goto end
)

REM Start Ollama if not running
curl -s http://localhost:11434/api/tags >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Starting Ollama...
    start /b ollama serve >nul 2>&1
    timeout /t 5 /nobreak >nul
)

call :configure_env

echo Starting Docker containers...
docker compose -f docker-compose.yml -f docker-compose.native-ollama.yml up -d

echo.
echo OpenEntity is running!
echo.
echo Frontend: http://localhost:8080
echo API:      http://localhost:8080/api/v1
goto end

:stop
echo Stopping Docker containers...
docker compose down
echo Stopped
goto end

:status
echo Ollama:
curl -s http://localhost:11434/api/tags >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   Status: Running
    echo   Models:
    ollama list 2>nul
) else (
    echo   Status: Not running
)
echo.
echo Docker:
docker compose ps 2>nul
goto end

:info
echo Usage: setup.bat [command]
echo.
echo Commands:
echo   install    Install Ollama and pull models
echo   start      Start Ollama and Docker
echo   stop       Stop Docker containers
echo   status     Show status
echo   help       Show this help
echo.
echo For full functionality, use PowerShell:
echo   powershell -ExecutionPolicy Bypass -File setup.ps1 install
goto end

:help
goto info

:configure_env
if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
        echo Created .env from .env.example
    )
)

REM Update .env settings using PowerShell for reliability
powershell -Command "$env = Get-Content '.env' -Raw; $env = $env -replace '(?m)^OLLAMA_BASE_URL=.*$', 'OLLAMA_BASE_URL=http://host.docker.internal:11434'; $env = $env -replace '(?m)^#OLLAMA_BASE_URL=.*$', 'OLLAMA_BASE_URL=http://host.docker.internal:11434'; if ($env -notmatch 'OLLAMA_BASE_URL=') { $env += \"`nOLLAMA_BASE_URL=http://host.docker.internal:11434\" }; $env = $env -replace '(?m)^OLLAMA_PORT=.*$', 'OLLAMA_PORT=11435'; $env = $env -replace '(?m)^#OLLAMA_PORT=.*$', 'OLLAMA_PORT=11435'; if ($env -notmatch 'OLLAMA_PORT=') { $env += \"`nOLLAMA_PORT=11435\" }; $env = $env -replace '(?m)^COMPOSE_FILE=.*$', 'COMPOSE_FILE=docker-compose.yml:docker-compose.native-ollama.yml'; $env = $env -replace '(?m)^#COMPOSE_FILE=.*$', 'COMPOSE_FILE=docker-compose.yml:docker-compose.native-ollama.yml'; if ($env -notmatch 'COMPOSE_FILE=') { $env += \"`nCOMPOSE_FILE=docker-compose.yml:docker-compose.native-ollama.yml\" }; Set-Content '.env' $env.TrimEnd()"

echo Configured .env for native Ollama
exit /b 0

:end
echo.

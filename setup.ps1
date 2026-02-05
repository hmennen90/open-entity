# OpenEntity Setup Script for Windows
# Run with: powershell -ExecutionPolicy Bypass -File setup.ps1
# Or: .\setup.ps1 (if execution policy allows)

param(
    [Parameter(Position=0)]
    [string]$Command = "",
    [string]$Model = "",
    [switch]$DockerOnly,
    [switch]$Help
)

$ErrorActionPreference = "Stop"

# Change to script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

Write-Host ""
Write-Host "OpenEntity Setup" -ForegroundColor Magenta
Write-Host "================" -ForegroundColor Magenta
Write-Host ""

# Detect GPU and Memory
$GPU_TYPE = "cpu"
$TOTAL_MEM_GB = 8

# Allow override for CI testing
if ($env:TEST_MEMORY_GB) {
    $TOTAL_MEM_GB = [int]$env:TEST_MEMORY_GB
    Write-Host "Platform: CI Test Mode" -ForegroundColor Cyan
    Write-Host "         Simulated $TOTAL_MEM_GB GB RAM" -ForegroundColor Gray
} else {
    # Check for NVIDIA GPU
    try {
        $nvidiaSmi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
        if ($nvidiaSmi) {
            $gpuInfo = nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>$null
            if ($LASTEXITCODE -eq 0 -and $gpuInfo) {
                $GPU_TYPE = "nvidia"
                $vramMB = (nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>$null | Select-Object -First 1) -as [int]
                $TOTAL_MEM_GB = [math]::Floor($vramMB / 1024)
                Write-Host "Platform: Windows with NVIDIA GPU" -ForegroundColor Green
                Write-Host "         $gpuInfo" -ForegroundColor Gray
            }
        }
    } catch {}

    if ($GPU_TYPE -eq "cpu") {
        $TOTAL_MEM_GB = [math]::Floor((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory / 1GB)
        Write-Host "Platform: Windows (CPU only)" -ForegroundColor Yellow
        Write-Host "         $TOTAL_MEM_GB GB RAM" -ForegroundColor Gray
    }
}

# Select model based on available memory
function Select-Model {
    param([int]$mem)
    if ($mem -lt 6) { return "qwen2.5:3b" }
    elseif ($mem -lt 10) { return "qwen2.5:7b" }
    elseif ($mem -lt 18) { return "qwen2.5:14b" }
    elseif ($mem -lt 28) { return "qwen2.5:14b" }
    elseif ($mem -lt 48) { return "qwen2.5:32b" }
    else { return "qwen2.5:72b" }
}

$RECOMMENDED_MODEL = Select-Model $TOTAL_MEM_GB
Write-Host "         Recommended model: $RECOMMENDED_MODEL" -ForegroundColor Cyan
Write-Host ""

# Check if Ollama is installed
function Test-OllamaInstalled {
    try {
        $ollamaPath = Get-Command ollama -ErrorAction SilentlyContinue
        return $null -ne $ollamaPath
    } catch {
        return $false
    }
}

# Check if Ollama is running
function Test-OllamaRunning {
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -TimeoutSec 5 -UseBasicParsing -ErrorAction SilentlyContinue
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

# Install Ollama
function Install-Ollama {
    Write-Host "Installing Ollama..." -ForegroundColor Blue

    # Check if winget is available
    $winget = Get-Command winget -ErrorAction SilentlyContinue

    if ($winget) {
        Write-Host "Installing via winget..." -ForegroundColor Gray
        winget install Ollama.Ollama --accept-package-agreements --accept-source-agreements
    } else {
        # Download and install manually
        Write-Host "Downloading Ollama installer..." -ForegroundColor Gray
        $installerUrl = "https://ollama.ai/download/OllamaSetup.exe"
        $installerPath = "$env:TEMP\OllamaSetup.exe"

        Invoke-WebRequest -Uri $installerUrl -OutFile $installerPath -UseBasicParsing

        Write-Host "Running installer (may require admin privileges)..." -ForegroundColor Yellow
        Start-Process -FilePath $installerPath -Wait

        Remove-Item $installerPath -ErrorAction SilentlyContinue
    }

    # Refresh PATH
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")

    Write-Host "Ollama installed successfully" -ForegroundColor Green
}

# Start Ollama
function Start-OllamaService {
    if (Test-OllamaRunning) {
        Write-Host "Ollama is already running" -ForegroundColor Green
        return
    }

    Write-Host "Starting Ollama..." -ForegroundColor Blue

    # Start Ollama in background
    Start-Process -FilePath "ollama" -ArgumentList "serve" -WindowStyle Hidden

    # Wait for Ollama to be ready
    $retries = 30
    while (-not (Test-OllamaRunning) -and $retries -gt 0) {
        Start-Sleep -Seconds 1
        $retries--
    }

    if ($retries -le 0) {
        Write-Host "Failed to start Ollama" -ForegroundColor Red
        exit 1
    }

    Write-Host "Ollama is running" -ForegroundColor Green
}

# Pull models
function Pull-Models {
    param([string]$ModelName)

    Write-Host "Pulling LLM model: $ModelName..." -ForegroundColor Blue
    & ollama pull $ModelName

    Write-Host "Pulling embedding model: nomic-embed-text..." -ForegroundColor Blue
    & ollama pull nomic-embed-text

    Write-Host "Models ready" -ForegroundColor Green
}

# Configure .env file
function Configure-Env {
    param([string]$ModelName)

    $envFile = Join-Path $ScriptDir ".env"
    $envExample = Join-Path $ScriptDir ".env.example"

    if (-not (Test-Path $envFile)) {
        if (Test-Path $envExample) {
            Copy-Item $envExample $envFile
            Write-Host "Created .env from .env.example" -ForegroundColor Green
        } else {
            Write-Host ".env.example not found" -ForegroundColor Red
            return
        }
    }

    # On Windows, Docker Desktop supports host.docker.internal
    $ollamaUrl = "http://host.docker.internal:11434"

    # Read current .env content
    $envContent = Get-Content $envFile -Raw

    # Update or add settings
    function Update-EnvVar {
        param([string]$Content, [string]$Key, [string]$Value)

        if ($Content -match "(?m)^$Key=.*$") {
            return $Content -replace "(?m)^$Key=.*$", "$Key=$Value"
        } elseif ($Content -match "(?m)^#$Key=.*$") {
            return $Content -replace "(?m)^#$Key=.*$", "$Key=$Value"
        } else {
            return $Content + "`n$Key=$Value"
        }
    }

    $envContent = Update-EnvVar $envContent "OLLAMA_BASE_URL" $ollamaUrl
    $envContent = Update-EnvVar $envContent "OLLAMA_MODEL" $ModelName
    $envContent = Update-EnvVar $envContent "OLLAMA_PORT" "11435"  # Avoid conflict with native Ollama
    $envContent = Update-EnvVar $envContent "COMPOSE_FILE" "docker-compose.yml:docker-compose.native-ollama.yml"

    Set-Content -Path $envFile -Value $envContent.TrimEnd()

    Write-Host "Configured .env for native Ollama" -ForegroundColor Green
    Write-Host "         OLLAMA_BASE_URL=$ollamaUrl" -ForegroundColor Gray
    Write-Host "         OLLAMA_MODEL=$ModelName" -ForegroundColor Gray
}

# Start Docker containers
function Start-Docker {
    Write-Host "Starting Docker containers..." -ForegroundColor Blue

    # Use native-ollama override
    & docker compose -f docker-compose.yml -f docker-compose.native-ollama.yml up -d

    Write-Host ""
    Write-Host "OpenEntity is running!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Frontend: http://localhost:8080" -ForegroundColor Cyan
    Write-Host "API:      http://localhost:8080/api/v1" -ForegroundColor Cyan
}

# Show help
function Show-Help {
    Write-Host "Usage: .\setup.ps1 [command] [options]" -ForegroundColor White
    Write-Host ""
    Write-Host "Commands:" -ForegroundColor Cyan
    Write-Host "  install          Install Ollama and pull models (recommended first run)"
    Write-Host "  start            Start Ollama and Docker containers"
    Write-Host "  stop             Stop Docker containers"
    Write-Host "  status           Show status of all services"
    Write-Host "  pull-models      Pull/update LLM models"
    Write-Host "  help             Show this help"
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Cyan
    Write-Host "  -Model NAME      Use specific model (default: auto-selected based on RAM)"
    Write-Host "  -DockerOnly      Start only Docker containers (Ollama must be running)"
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Cyan
    Write-Host "  .\setup.ps1 install              # First-time setup"
    Write-Host "  .\setup.ps1 start                # Start everything"
    Write-Host "  .\setup.ps1 start -Model qwen2.5:7b"
    Write-Host ""
    Write-Host "If execution policy error:" -ForegroundColor Yellow
    Write-Host "  powershell -ExecutionPolicy Bypass -File setup.ps1 install"
    Write-Host ""
    Write-Host "Recommended models by memory:" -ForegroundColor Cyan
    Write-Host "  < 6 GB:   qwen2.5:3b"
    Write-Host "  6-10 GB:  qwen2.5:7b"
    Write-Host "  10-18 GB: qwen2.5:14b"
    Write-Host "  18-28 GB: qwen2.5:14b (full precision)"
    Write-Host "  28-48 GB: qwen2.5:32b"
    Write-Host "  > 48 GB:  qwen2.5:72b"
}

# Use recommended model if not specified
if ([string]::IsNullOrEmpty($Model)) {
    $Model = $RECOMMENDED_MODEL
}

# Handle -Help switch
if ($Help) {
    Show-Help
    exit 0
}

# Execute command
switch ($Command.ToLower()) {
    "install" {
        if (-not (Test-OllamaInstalled)) {
            Install-Ollama
        } else {
            Write-Host "Ollama is already installed" -ForegroundColor Green
        }
        Start-OllamaService
        Pull-Models $Model
        Configure-Env $Model
        Write-Host ""
        Write-Host "Installation complete!" -ForegroundColor Green
        Write-Host "Run " -NoNewline
        Write-Host ".\setup.ps1 start" -ForegroundColor Cyan -NoNewline
        Write-Host " to start OpenEntity"
    }

    "start" {
        if (-not $DockerOnly) {
            if (-not (Test-OllamaInstalled)) {
                Write-Host "Ollama is not installed. Run '.\setup.ps1 install' first." -ForegroundColor Red
                exit 1
            }
            Start-OllamaService
        }
        Configure-Env $Model
        Start-Docker
    }

    "stop" {
        Write-Host "Stopping Docker containers..." -ForegroundColor Blue
        & docker compose down
        Write-Host "Stopped" -ForegroundColor Green
    }

    "status" {
        Write-Host "Ollama:" -ForegroundColor Blue
        if (Test-OllamaRunning) {
            Write-Host "  Status: Running" -ForegroundColor Green
            Write-Host "  Models:"
            & ollama list 2>$null | ForEach-Object { Write-Host "    $_" }
        } else {
            Write-Host "  Status: Not running" -ForegroundColor Red
        }
        Write-Host ""
        Write-Host "Docker:" -ForegroundColor Blue
        & docker compose ps 2>$null | ForEach-Object { Write-Host "  $_" }
    }

    "pull-models" {
        Start-OllamaService
        Pull-Models $Model
    }

    "help" {
        Show-Help
    }

    "" {
        Write-Host "System detected: Windows, $GPU_TYPE GPU, $TOTAL_MEM_GB GB memory"
        Write-Host ""
        Show-Help
    }

    default {
        Write-Host "Unknown command: $Command" -ForegroundColor Red
        Write-Host ""
        Show-Help
        exit 1
    }
}

Write-Host ""

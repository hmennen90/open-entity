# OpenEntity Setup Script für Windows
# Führe aus mit: powershell -ExecutionPolicy Bypass -File setup.ps1

param(
    [switch]$Start,
    [switch]$Help
)

Write-Host ""
Write-Host "OpenEntity Setup" -ForegroundColor Magenta
Write-Host "================" -ForegroundColor Magenta
Write-Host ""

# GPU erkennen
$GPU_TYPE = "none"
$COMPOSE_CMD = "docker compose"

try {
    $nvidiaSmi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
    if ($nvidiaSmi) {
        $gpuInfo = nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>$null
        if ($LASTEXITCODE -eq 0 -and $gpuInfo) {
            $GPU_TYPE = "nvidia"
            $COMPOSE_CMD = "docker compose -f docker-compose.yml -f docker-compose.gpu.yml"
            Write-Host "GPU:     NVIDIA erkannt" -ForegroundColor Green
            Write-Host "         $gpuInfo" -ForegroundColor Gray
        }
    }
} catch {}

if ($GPU_TYPE -eq "none") {
    Write-Host "GPU:     Keine NVIDIA GPU (CPU-Modus)" -ForegroundColor Yellow
}

Write-Host ""

if ($Help) {
    Write-Host "Verwendung:" -ForegroundColor Cyan
    Write-Host "  .\setup.ps1          Zeigt Hardware-Info"
    Write-Host "  .\setup.ps1 -Start   Startet Docker Container"
    Write-Host "  .\setup.ps1 -Help    Diese Hilfe"
    Write-Host ""
    Write-Host "Bei Execution Policy Fehler:" -ForegroundColor Yellow
    Write-Host "  powershell -ExecutionPolicy Bypass -File setup.ps1 -Start"
    Write-Host ""
    Write-Host "Oder manuell starten:" -ForegroundColor Cyan
    if ($GPU_TYPE -eq "nvidia") {
        Write-Host "  docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d"
    } else {
        Write-Host "  docker compose up -d"
    }
    exit
}

if ($Start) {
    Write-Host "Starte Container..." -ForegroundColor Cyan
    Write-Host "> $COMPOSE_CMD up -d" -ForegroundColor Gray
    Write-Host ""

    if ($GPU_TYPE -eq "nvidia") {
        & docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d
    } else {
        & docker compose up -d
    }

    Write-Host ""
    Write-Host "Container gestartet!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Frontend: http://localhost:8080" -ForegroundColor White
    Write-Host "API:      http://localhost:8080/api/v1" -ForegroundColor White
} else {
    Write-Host "Zum Starten:" -ForegroundColor Cyan
    Write-Host ""
    if ($GPU_TYPE -eq "nvidia") {
        Write-Host "  .\setup.ps1 -Start" -ForegroundColor White
        Write-Host ""
        Write-Host "  oder manuell:" -ForegroundColor Gray
        Write-Host "  docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d" -ForegroundColor Gray
    } else {
        Write-Host "  .\setup.ps1 -Start" -ForegroundColor White
        Write-Host ""
        Write-Host "  oder manuell:" -ForegroundColor Gray
        Write-Host "  docker compose up -d" -ForegroundColor Gray
    }
}

Write-Host ""

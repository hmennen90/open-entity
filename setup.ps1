# OpenEntity Setup Script für Windows
# Automatische GPU-Erkennung und Ollama-Konfiguration

$ErrorActionPreference = "Stop"

Write-Host "🔍 Erkenne Hardware..." -ForegroundColor Cyan

$GPU_TYPE = "none"
$COMPOSE_FILES = "-f docker-compose.yml"
$OLLAMA_HOST = ""
$MEMORY_GB = 0

# Standardmodelle für OpenEntity
$EMBEDDING_MODEL = if ($env:EMBEDDING_OLLAMA_MODEL) { $env:EMBEDDING_OLLAMA_MODEL } else { "nomic-embed-text" }

# Funktion: Modell basierend auf verfügbarem Speicher wählen
function Select-ModelByMemory {
    param([int]$MemGB)

    if ($MemGB -lt 6) {
        return "qwen2.5:7b-instruct-q4_K_M"      # < 6 GB: 7B q4 (4.7 GB)
    } elseif ($MemGB -lt 10) {
        return "qwen2.5:7b-instruct-q5_K_M"      # 6-10 GB: 7B q5 (5.4 GB)
    } elseif ($MemGB -lt 14) {
        return "qwen2.5:14b-instruct-q4_K_M"     # 10-14 GB: 14B q4 (9 GB)
    } elseif ($MemGB -lt 22) {
        return "qwen2.5:14b-instruct-q5_K_M"     # 14-22 GB: 14B q5 (11 GB)
    } elseif ($MemGB -lt 26) {
        return "qwen2.5:32b-instruct-q4_K_M"     # 22-26 GB: 32B q4 (20 GB)
    } elseif ($MemGB -lt 50) {
        return "qwen2.5:32b-instruct-q5_K_M"     # 26-50 GB: 32B q5 (23 GB)
    } elseif ($MemGB -lt 58) {
        return "qwen2.5:72b-instruct-q4_K_M"     # 50-58 GB: 72B q4 (47 GB)
    } else {
        return "qwen2.5:72b-instruct-q5_K_M"     # > 58 GB: 72B q5 (54 GB)
    }
}

# NVIDIA GPU prüfen
try {
    $nvidiaSmi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
    if ($nvidiaSmi) {
        $gpuInfo = nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>$null
        if ($LASTEXITCODE -eq 0 -and $gpuInfo) {
            $GPU_TYPE = "nvidia"
            $COMPOSE_FILES = "-f docker-compose.yml -f docker-compose.gpu.yml"

            # VRAM in GB
            $VRAM_MB = [int]($gpuInfo -split "`n")[0].Trim()
            $MEMORY_GB = [math]::Floor($VRAM_MB / 1024)

            Write-Host "✅ NVIDIA GPU erkannt ($MEMORY_GB GB VRAM)" -ForegroundColor Green
        }
    }
} catch {
    # Keine NVIDIA GPU
}

# AMD GPU prüfen (DirectX/DXGI)
if ($GPU_TYPE -eq "none") {
    try {
        $amdGpu = Get-WmiObject Win32_VideoController | Where-Object { $_.Name -match "AMD|Radeon" }
        if ($amdGpu) {
            $GPU_TYPE = "amd"
            # AdapterRAM ist in Bytes
            $VRAM_BYTES = $amdGpu.AdapterRAM
            if ($VRAM_BYTES -gt 0) {
                $MEMORY_GB = [math]::Floor($VRAM_BYTES / 1GB)
                Write-Host "✅ AMD GPU erkannt ($MEMORY_GB GB VRAM)" -ForegroundColor Green
            } else {
                $MEMORY_GB = 8  # Fallback
                Write-Host "✅ AMD GPU erkannt" -ForegroundColor Green
            }
        }
    } catch {
        # Keine AMD GPU
    }
}

# CPU-only Modus mit System-RAM
if ($GPU_TYPE -eq "none") {
    $totalMemory = (Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory
    $MEMORY_GB = [math]::Floor($totalMemory / 1GB)

    Write-Host "ℹ️  Keine GPU erkannt - verwende CPU-Modus ($MEMORY_GB GB RAM)" -ForegroundColor Yellow
    Write-Host "   ⚠️  CPU-Inferenz ist langsam, kleinere Modelle empfohlen" -ForegroundColor Yellow
}

# Modell wählen
if ($env:OLLAMA_MODEL) {
    $LLM_MODEL = $env:OLLAMA_MODEL
} elseif ($GPU_TYPE -eq "none") {
    # CPU: Konservativere Modellwahl
    if ($MEMORY_GB -lt 10) {
        $LLM_MODEL = "qwen2.5:7b-instruct-q4_K_M"
    } elseif ($MEMORY_GB -lt 16) {
        $LLM_MODEL = "qwen2.5:7b-instruct-q5_K_M"
    } else {
        $LLM_MODEL = "qwen2.5:14b-instruct-q4_K_M"
    }
} else {
    $LLM_MODEL = Select-ModelByMemory -MemGB $MEMORY_GB
}

Write-Host "   Gewähltes Modell: $LLM_MODEL" -ForegroundColor White

# Funktion: Ollama-Modelle pullen
function Pull-OllamaModels {
    param([string]$Host)

    Write-Host ""
    Write-Host "📦 Lade Ollama-Modelle..." -ForegroundColor Cyan
    Write-Host "   LLM-Modell: $LLM_MODEL"
    Write-Host "   Embedding-Modell: $EMBEDDING_MODEL"
    Write-Host ""

    # Prüfen ob Ollama erreichbar ist
    try {
        $null = Invoke-RestMethod -Uri "$Host/api/tags" -TimeoutSec 5
    } catch {
        Write-Host "⚠️  Ollama nicht erreichbar unter $Host" -ForegroundColor Red
        Write-Host "   Bitte stelle sicher, dass Ollama läuft." -ForegroundColor Red
        return $false
    }

    # LLM-Modell pullen
    Write-Host "⬇️  Lade $LLM_MODEL..." -ForegroundColor White
    try {
        $body = @{ name = $LLM_MODEL } | ConvertTo-Json
        $response = Invoke-RestMethod -Uri "$Host/api/pull" -Method Post -Body $body -ContentType "application/json"
        Write-Host "   ✅ $LLM_MODEL geladen" -ForegroundColor Green
    } catch {
        Write-Host "   ⚠️  Fehler beim Laden von $LLM_MODEL" -ForegroundColor Red
    }

    # Embedding-Modell pullen
    Write-Host "⬇️  Lade $EMBEDDING_MODEL..." -ForegroundColor White
    try {
        $body = @{ name = $EMBEDDING_MODEL } | ConvertTo-Json
        $response = Invoke-RestMethod -Uri "$Host/api/pull" -Method Post -Body $body -ContentType "application/json"
        Write-Host "   ✅ $EMBEDDING_MODEL geladen" -ForegroundColor Green
    } catch {
        Write-Host "   ⚠️  Fehler beim Laden von $EMBEDDING_MODEL" -ForegroundColor Red
    }

    return $true
}

# Funktion: Ollama auf Windows installieren/starten
function Setup-OllamaWindows {
    Write-Host ""
    Write-Host "🪟 Windows Ollama Setup..." -ForegroundColor Cyan

    # Prüfen ob Ollama installiert ist
    $ollamaPath = Get-Command ollama -ErrorAction SilentlyContinue
    if (-not $ollamaPath) {
        Write-Host "   Ollama nicht gefunden." -ForegroundColor Yellow
        Write-Host "   Bitte installiere Ollama von: https://ollama.ai/download" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "   Nach der Installation:" -ForegroundColor White
        Write-Host "   1. Starte Ollama" -ForegroundColor White
        Write-Host "   2. Führe dieses Script erneut aus" -ForegroundColor White
        return $false
    }

    # Prüfen ob Ollama läuft
    try {
        $null = Invoke-RestMethod -Uri "http://localhost:11434/api/tags" -TimeoutSec 2
        Write-Host "   ✅ Ollama läuft" -ForegroundColor Green
    } catch {
        Write-Host "   Starte Ollama..." -ForegroundColor White
        Start-Process ollama -ArgumentList "serve" -WindowStyle Hidden
        Start-Sleep -Seconds 3
        Write-Host "   ✅ Ollama gestartet" -ForegroundColor Green
    }

    return $true
}

# Hauptlogik
Write-Host ""
Write-Host "🚀 OpenEntity Setup" -ForegroundColor Magenta
Write-Host "===================" -ForegroundColor Magenta

$action = $args[0]

switch ($action) {
    "--start" {
        Write-Host ""
        Write-Host "📦 Starte Docker Container..." -ForegroundColor Cyan

        $composeArgs = $COMPOSE_FILES -split " "
        & docker compose @composeArgs up -d

        Write-Host "⏳ Warte auf Ollama Container..." -ForegroundColor White
        Start-Sleep -Seconds 10
        $OLLAMA_HOST = "http://localhost:11434"

        Pull-OllamaModels -Host $OLLAMA_HOST

        Write-Host ""
        Write-Host "✅ Setup abgeschlossen!" -ForegroundColor Green
        Write-Host ""
        Write-Host "   Frontend: http://localhost:8080" -ForegroundColor White
        Write-Host "   API:      http://localhost:8080/api/v1" -ForegroundColor White
    }

    "--pull-models" {
        $OLLAMA_HOST = if ($env:OLLAMA_BASE_URL) { $env:OLLAMA_BASE_URL } else { "http://localhost:11434" }
        Pull-OllamaModels -Host $OLLAMA_HOST
    }

    "--setup-native" {
        # Native Ollama Installation auf Windows
        if (Setup-OllamaWindows) {
            Pull-OllamaModels -Host "http://localhost:11434"

            Write-Host ""
            Write-Host "📝 Füge folgende Zeile zur .env hinzu:" -ForegroundColor Yellow
            Write-Host "   OLLAMA_BASE_URL=http://host.docker.internal:11434" -ForegroundColor White
        }
    }

    { $_ -in "--help", "-h", "-?" } {
        Write-Host ""
        Write-Host "Verwendung: .\setup.ps1 [OPTION]" -ForegroundColor White
        Write-Host ""
        Write-Host "Optionen:" -ForegroundColor Cyan
        Write-Host "  --start        Startet Docker und lädt Ollama-Modelle"
        Write-Host "  --pull-models  Lädt nur die Ollama-Modelle"
        Write-Host "  --setup-native Installiert/startet Ollama nativ auf Windows"
        Write-Host "  --help         Zeigt diese Hilfe"
        Write-Host ""
        Write-Host "Erkannte Hardware:" -ForegroundColor Cyan
        Write-Host "  Typ:       $GPU_TYPE"
        Write-Host "  Speicher:  $MEMORY_GB GB"
        Write-Host ""
        Write-Host "Automatische Modellwahl (q5_K_M bevorzugt, q4_K_M bei knappem Speicher):" -ForegroundColor Cyan
        Write-Host "  < 6 GB:   qwen2.5:7b-instruct-q4_K_M   (4.7 GB)"
        Write-Host "  6-10 GB:  qwen2.5:7b-instruct-q5_K_M   (5.4 GB)"
        Write-Host "  10-14 GB: qwen2.5:14b-instruct-q4_K_M  (9 GB)"
        Write-Host "  14-22 GB: qwen2.5:14b-instruct-q5_K_M  (11 GB)"
        Write-Host "  22-26 GB: qwen2.5:32b-instruct-q4_K_M  (20 GB)"
        Write-Host "  26-50 GB: qwen2.5:32b-instruct-q5_K_M  (23 GB)"
        Write-Host "  50-58 GB: qwen2.5:72b-instruct-q4_K_M  (47 GB)"
        Write-Host "  > 58 GB:  qwen2.5:72b-instruct-q5_K_M  (54 GB)"
        Write-Host ""
        Write-Host "Gewählte Modelle:" -ForegroundColor Cyan
        Write-Host "  LLM:       $LLM_MODEL"
        Write-Host "  Embedding: $EMBEDDING_MODEL"
        Write-Host ""
        Write-Host "Umgebungsvariablen (zum Überschreiben):" -ForegroundColor Cyan
        Write-Host "  OLLAMA_MODEL           Alternatives LLM-Modell"
        Write-Host "  EMBEDDING_OLLAMA_MODEL Alternatives Embedding-Modell"
        Write-Host "  OLLAMA_BASE_URL        Ollama Server URL"
    }

    default {
        Write-Host ""
        Write-Host "Hardware:  $GPU_TYPE ($MEMORY_GB GB)" -ForegroundColor White
        Write-Host "LLM:       $LLM_MODEL" -ForegroundColor White
        Write-Host "Embedding: $EMBEDDING_MODEL" -ForegroundColor White
        Write-Host ""
        Write-Host "Docker:    docker compose $COMPOSE_FILES up -d" -ForegroundColor Gray
        Write-Host ""
        Write-Host "Nächste Schritte:" -ForegroundColor Cyan
        Write-Host "  .\setup.ps1 --start        # Alles starten"
        Write-Host "  .\setup.ps1 --setup-native # Native Ollama Installation"
        Write-Host "  .\setup.ps1 --help         # Mehr Optionen"
    }
}

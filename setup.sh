#!/bin/bash
# OpenEntity Setup Script mit automatischer GPU-Erkennung

echo "🔍 Erkenne Hardware..."

GPU_TYPE="none"
COMPOSE_FILES="-f docker-compose.yml"

# NVIDIA GPU prüfen
if command -v nvidia-smi &> /dev/null; then
    if nvidia-smi &> /dev/null; then
        GPU_TYPE="nvidia"
        COMPOSE_FILES="-f docker-compose.yml -f docker-compose.gpu.yml"
        echo "✅ NVIDIA GPU erkannt"
    fi
fi

# AMD GPU prüfen (ROCm)
if [ "$GPU_TYPE" = "none" ] && command -v rocm-smi &> /dev/null; then
    GPU_TYPE="amd"
    echo "✅ AMD GPU erkannt (ROCm) - manuelles Setup erforderlich"
fi

# Apple Silicon prüfen
if [ "$GPU_TYPE" = "none" ] && [[ "$(uname -m)" == "arm64" ]] && [[ "$(uname)" == "Darwin" ]]; then
    GPU_TYPE="apple"
    echo "✅ Apple Silicon erkannt"
    echo "   Empfehlung: Ollama nativ installieren für Metal-Beschleunigung"
    echo "   brew install ollama && ollama serve"
    echo "   Dann in .env: OLLAMA_BASE_URL=http://host.docker.internal:11434"
fi

if [ "$GPU_TYPE" = "none" ]; then
    echo "ℹ️  Keine GPU erkannt - verwende CPU-Modus"
fi

echo ""
echo "🚀 Starte mit: docker compose $COMPOSE_FILES up -d"
echo ""

# Optional: direkt starten
if [ "$1" = "--start" ]; then
    docker compose $COMPOSE_FILES up -d
fi

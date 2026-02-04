#!/bin/bash
# OpenEntity Setup Script für Linux und macOS

echo ""
echo "OpenEntity Setup"
echo "================"
echo ""

GPU_TYPE="none"
COMPOSE_CMD="docker compose"

# NVIDIA GPU prüfen
if command -v nvidia-smi &> /dev/null && nvidia-smi &> /dev/null; then
    GPU_TYPE="nvidia"
    COMPOSE_CMD="docker compose -f docker-compose.yml -f docker-compose.gpu.yml"
    GPU_INFO=$(nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>/dev/null | head -1)
    echo "GPU:     NVIDIA erkannt"
    echo "         $GPU_INFO"
fi

# AMD GPU prüfen (ROCm)
if [ "$GPU_TYPE" = "none" ] && command -v rocm-smi &> /dev/null; then
    GPU_TYPE="amd"
    echo "GPU:     AMD erkannt (ROCm)"
    echo "         Hinweis: docker-compose.amd.yml erstellen für ROCm-Support"
fi

# Apple Silicon prüfen
if [ "$GPU_TYPE" = "none" ] && [[ "$(uname -m)" == "arm64" ]] && [[ "$(uname)" == "Darwin" ]]; then
    GPU_TYPE="apple"
    MEM_GB=$(($(sysctl -n hw.memsize 2>/dev/null) / 1024 / 1024 / 1024))
    echo "GPU:     Apple Silicon (${MEM_GB} GB Unified Memory)"
    echo ""
    echo "Empfehlung: Ollama nativ installieren für Metal-Beschleunigung"
    echo "  brew install ollama && ollama serve"
    echo "  Dann in .env: OLLAMA_BASE_URL=http://host.docker.internal:11434"
fi

# CPU-only
if [ "$GPU_TYPE" = "none" ]; then
    echo "GPU:     Keine erkannt (CPU-Modus)"
fi

echo ""

case "$1" in
    --start|-s)
        echo "Starte Container..."
        echo "> $COMPOSE_CMD up -d"
        echo ""
        $COMPOSE_CMD up -d
        echo ""
        echo "Container gestartet!"
        echo ""
        echo "Frontend: http://localhost:8080"
        echo "API:      http://localhost:8080/api/v1"
        ;;

    --native|-n)
        if [ "$GPU_TYPE" != "apple" ]; then
            echo "Dieser Befehl ist nur für Apple Silicon gedacht."
            exit 1
        fi

        echo "Native Ollama Setup für Apple Silicon..."
        echo ""

        if ! command -v ollama &> /dev/null; then
            if command -v brew &> /dev/null; then
                echo "Installiere Ollama..."
                brew install ollama
            else
                echo "Bitte installiere Ollama manuell: https://ollama.ai/download"
                exit 1
            fi
        fi

        if ! curl -s "http://localhost:11434/api/tags" > /dev/null 2>&1; then
            echo "Starte Ollama..."
            ollama serve &
            sleep 3
        fi

        echo "Ollama läuft auf http://localhost:11434"
        echo ""
        echo "Füge zu .env hinzu:"
        echo "  OLLAMA_BASE_URL=http://host.docker.internal:11434"
        ;;

    --help|-h)
        echo "Verwendung:"
        echo "  ./setup.sh           Zeigt Hardware-Info"
        echo "  ./setup.sh --start   Startet Docker Container"
        echo "  ./setup.sh --native  Apple Silicon: Installiert Ollama nativ"
        echo "  ./setup.sh --help    Diese Hilfe"
        echo ""
        echo "Manuell starten:"
        echo "  $COMPOSE_CMD up -d"
        ;;

    *)
        echo "Zum Starten:"
        echo ""
        echo "  ./setup.sh --start"
        echo ""
        echo "  oder manuell:"
        echo "  $COMPOSE_CMD up -d"

        if [ "$GPU_TYPE" = "apple" ]; then
            echo ""
            echo "Für beste Performance auf Apple Silicon:"
            echo "  ./setup.sh --native"
        fi
        ;;
esac

echo ""

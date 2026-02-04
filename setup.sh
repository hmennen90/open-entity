#!/bin/bash
# OpenEntity Setup Script mit automatischer GPU-Erkennung und Ollama-Konfiguration

set -e

echo "🔍 Erkenne Hardware..."

GPU_TYPE="none"
COMPOSE_FILES="-f docker-compose.yml"
OLLAMA_HOST=""
MEMORY_GB=0

# Standardmodelle für OpenEntity (werden je nach Hardware angepasst)
EMBEDDING_MODEL="${EMBEDDING_OLLAMA_MODEL:-nomic-embed-text}"

# Funktion: Modell basierend auf verfügbarem Speicher wählen
# Verwendet q5_K_M für beste Qualität, q4_K_M als Fallback bei knappem Speicher
select_model_by_memory() {
    local mem_gb=$1

    if [ "$mem_gb" -lt 6 ]; then
        # < 6 GB: 7B mit q4 (4.7 GB)
        echo "qwen2.5:7b-instruct-q4_K_M"
    elif [ "$mem_gb" -lt 10 ]; then
        # 6-10 GB: 7B mit q5 (5.4 GB) - Headroom für Context
        echo "qwen2.5:7b-instruct-q5_K_M"
    elif [ "$mem_gb" -lt 14 ]; then
        # 10-14 GB: 14B mit q4 (9 GB)
        echo "qwen2.5:14b-instruct-q4_K_M"
    elif [ "$mem_gb" -lt 22 ]; then
        # 14-22 GB: 14B mit q5 (11 GB) - Headroom für Context
        echo "qwen2.5:14b-instruct-q5_K_M"
    elif [ "$mem_gb" -lt 26 ]; then
        # 22-26 GB: 32B mit q4 (20 GB)
        echo "qwen2.5:32b-instruct-q4_K_M"
    elif [ "$mem_gb" -lt 50 ]; then
        # 26-50 GB: 32B mit q5 (23 GB)
        echo "qwen2.5:32b-instruct-q5_K_M"
    elif [ "$mem_gb" -lt 58 ]; then
        # 50-58 GB: 72B mit q4 (47 GB)
        echo "qwen2.5:72b-instruct-q4_K_M"
    else
        # > 58 GB: 72B mit q5 (54 GB)
        echo "qwen2.5:72b-instruct-q5_K_M"
    fi
}

# NVIDIA GPU prüfen
if command -v nvidia-smi &> /dev/null; then
    if nvidia-smi &> /dev/null; then
        GPU_TYPE="nvidia"
        COMPOSE_FILES="-f docker-compose.yml -f docker-compose.gpu.yml"

        # VRAM in GB ermitteln
        VRAM_MB=$(nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>/dev/null | head -1)
        if [ -n "$VRAM_MB" ]; then
            MEMORY_GB=$((VRAM_MB / 1024))
            echo "✅ NVIDIA GPU erkannt (${MEMORY_GB} GB VRAM)"
        else
            MEMORY_GB=8  # Fallback
            echo "✅ NVIDIA GPU erkannt"
        fi

        # Modell basierend auf VRAM wählen (wenn nicht manuell gesetzt)
        if [ -z "$OLLAMA_MODEL" ]; then
            LLM_MODEL=$(select_model_by_memory $MEMORY_GB)
        else
            LLM_MODEL="$OLLAMA_MODEL"
        fi
    fi
fi

# AMD GPU prüfen (ROCm)
if [ "$GPU_TYPE" = "none" ] && command -v rocm-smi &> /dev/null; then
    GPU_TYPE="amd"

    # VRAM ermitteln (ROCm)
    VRAM_MB=$(rocm-smi --showmeminfo vram 2>/dev/null | grep "Total" | awk '{print $3}' | head -1)
    if [ -n "$VRAM_MB" ]; then
        MEMORY_GB=$((VRAM_MB / 1024))
        echo "✅ AMD GPU erkannt (${MEMORY_GB} GB VRAM)"
    else
        MEMORY_GB=8  # Fallback
        echo "✅ AMD GPU erkannt (ROCm)"
    fi

    if [ -z "$OLLAMA_MODEL" ]; then
        LLM_MODEL=$(select_model_by_memory $MEMORY_GB)
    else
        LLM_MODEL="$OLLAMA_MODEL"
    fi
fi

# Apple Silicon prüfen
if [ "$GPU_TYPE" = "none" ] && [[ "$(uname -m)" == "arm64" ]] && [[ "$(uname)" == "Darwin" ]]; then
    GPU_TYPE="apple"
    OLLAMA_HOST="http://localhost:11434"

    # Unified Memory in GB ermitteln
    MEMORY_BYTES=$(sysctl -n hw.memsize 2>/dev/null)
    if [ -n "$MEMORY_BYTES" ]; then
        MEMORY_GB=$((MEMORY_BYTES / 1024 / 1024 / 1024))
        echo "✅ Apple Silicon erkannt (${MEMORY_GB} GB Unified Memory)"
    else
        MEMORY_GB=16  # Fallback
        echo "✅ Apple Silicon erkannt"
    fi

    if [ -z "$OLLAMA_MODEL" ]; then
        LLM_MODEL=$(select_model_by_memory $MEMORY_GB)
    else
        LLM_MODEL="$OLLAMA_MODEL"
    fi

    echo "   Empfehlung: Ollama nativ installieren für Metal-Beschleunigung"
fi

# CPU-only Modus (Linux/Windows ohne GPU)
if [ "$GPU_TYPE" = "none" ]; then
    # System RAM ermitteln
    if command -v free &> /dev/null; then
        MEMORY_GB=$(free -g 2>/dev/null | awk '/^Mem:/{print $2}')
    fi

    if [ "$MEMORY_GB" -eq 0 ]; then
        MEMORY_GB=8  # Fallback
    fi

    echo "ℹ️  Keine GPU erkannt - verwende CPU-Modus (${MEMORY_GB} GB RAM)"
    echo "   ⚠️  CPU-Inferenz ist langsam, kleinere Modelle empfohlen"

    # Für CPU kleinere Modelle empfehlen (langsamer)
    if [ -z "$OLLAMA_MODEL" ]; then
        if [ "$MEMORY_GB" -lt 10 ]; then
            LLM_MODEL="qwen2.5:7b-instruct-q4_K_M"
        elif [ "$MEMORY_GB" -lt 16 ]; then
            LLM_MODEL="qwen2.5:7b-instruct-q5_K_M"
        else
            LLM_MODEL="qwen2.5:14b-instruct-q4_K_M"
        fi
    else
        LLM_MODEL="$OLLAMA_MODEL"
    fi
fi

# Fallback LLM-Modell wenn nicht gesetzt
LLM_MODEL="${LLM_MODEL:-qwen2.5:7b-instruct-q5_K_M}"

echo "   Gewähltes Modell: $LLM_MODEL"

# Funktion: Ollama-Modelle pullen
pull_ollama_models() {
    local host="$1"

    echo ""
    echo "📦 Lade Ollama-Modelle..."
    echo "   LLM-Modell: $LLM_MODEL"
    echo "   Embedding-Modell: $EMBEDDING_MODEL"
    echo ""

    # Prüfen ob Ollama erreichbar ist
    if ! curl -s "$host/api/tags" > /dev/null 2>&1; then
        echo "⚠️  Ollama nicht erreichbar unter $host"
        echo "   Bitte stelle sicher, dass Ollama läuft."
        return 1
    fi

    # LLM-Modell pullen
    echo "⬇️  Lade $LLM_MODEL..."
    if curl -s "$host/api/pull" -d "{\"name\": \"$LLM_MODEL\"}" | while read -r line; do
        status=$(echo "$line" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        if [ -n "$status" ]; then
            printf "\r   %s" "$status"
        fi
    done; then
        echo ""
        echo "   ✅ $LLM_MODEL geladen"
    else
        echo ""
        echo "   ⚠️  Fehler beim Laden von $LLM_MODEL"
    fi

    # Embedding-Modell pullen
    echo "⬇️  Lade $EMBEDDING_MODEL..."
    if curl -s "$host/api/pull" -d "{\"name\": \"$EMBEDDING_MODEL\"}" | while read -r line; do
        status=$(echo "$line" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        if [ -n "$status" ]; then
            printf "\r   %s" "$status"
        fi
    done; then
        echo ""
        echo "   ✅ $EMBEDDING_MODEL geladen"
    else
        echo ""
        echo "   ⚠️  Fehler beim Laden von $EMBEDDING_MODEL"
    fi
}

# Funktion: Ollama auf macOS installieren/starten
setup_ollama_macos() {
    echo ""
    echo "🍎 Apple Silicon Setup..."

    # Prüfen ob Ollama installiert ist
    if ! command -v ollama &> /dev/null; then
        echo "   Ollama nicht gefunden. Installation..."
        if command -v brew &> /dev/null; then
            brew install ollama
        else
            echo "   ⚠️  Homebrew nicht gefunden. Bitte installiere Ollama manuell:"
            echo "   https://ollama.ai/download"
            return 1
        fi
    fi

    # Prüfen ob Ollama läuft
    if ! pgrep -x "ollama" > /dev/null && ! curl -s "http://localhost:11434/api/tags" > /dev/null 2>&1; then
        echo "   Starte Ollama..."
        ollama serve > /dev/null 2>&1 &
        sleep 3
    fi

    echo "   ✅ Ollama läuft"
}

# Hauptlogik
echo ""
echo "🚀 OpenEntity Setup"
echo "==================="

case "$1" in
    --start)
        echo ""
        echo "📦 Starte Docker Container..."
        docker compose $COMPOSE_FILES up -d

        # Warte auf Ollama Container (wenn nicht Apple Silicon)
        if [ "$GPU_TYPE" != "apple" ]; then
            echo "⏳ Warte auf Ollama Container..."
            sleep 10
            OLLAMA_HOST="http://localhost:11434"
        fi

        if [ -n "$OLLAMA_HOST" ]; then
            pull_ollama_models "$OLLAMA_HOST"
        fi

        echo ""
        echo "✅ Setup abgeschlossen!"
        echo ""
        echo "   Frontend: http://localhost:8080"
        echo "   API:      http://localhost:8080/api/v1"
        ;;

    --pull-models)
        # Nur Modelle pullen
        if [ "$GPU_TYPE" = "apple" ]; then
            setup_ollama_macos
            OLLAMA_HOST="http://localhost:11434"
        else
            OLLAMA_HOST="${OLLAMA_BASE_URL:-http://localhost:11434}"
        fi

        pull_ollama_models "$OLLAMA_HOST"
        ;;

    --setup-macos)
        # Komplettes macOS Setup
        setup_ollama_macos
        pull_ollama_models "http://localhost:11434"

        echo ""
        echo "📝 Füge folgende Zeile zur .env hinzu:"
        echo "   OLLAMA_BASE_URL=http://host.docker.internal:11434"
        ;;

    --help|-h)
        echo ""
        echo "Verwendung: ./setup.sh [OPTION]"
        echo ""
        echo "Optionen:"
        echo "  --start        Startet Docker und lädt Ollama-Modelle"
        echo "  --pull-models  Lädt nur die Ollama-Modelle"
        echo "  --setup-macos  Komplettes Setup für Apple Silicon"
        echo "  --help         Zeigt diese Hilfe"
        echo ""
        echo "Erkannte Hardware:"
        echo "  Typ:       $GPU_TYPE"
        echo "  Speicher:  ${MEMORY_GB} GB"
        echo ""
        echo "Automatische Modellwahl (q5_K_M bevorzugt, q4_K_M bei knappem Speicher):"
        echo "  < 6 GB:   qwen2.5:7b-instruct-q4_K_M   (4.7 GB)"
        echo "  6-10 GB:  qwen2.5:7b-instruct-q5_K_M   (5.4 GB)"
        echo "  10-14 GB: qwen2.5:14b-instruct-q4_K_M  (9 GB)"
        echo "  14-22 GB: qwen2.5:14b-instruct-q5_K_M  (11 GB)"
        echo "  22-26 GB: qwen2.5:32b-instruct-q4_K_M  (20 GB)"
        echo "  26-50 GB: qwen2.5:32b-instruct-q5_K_M  (23 GB)"
        echo "  50-58 GB: qwen2.5:72b-instruct-q4_K_M  (47 GB)"
        echo "  > 58 GB:  qwen2.5:72b-instruct-q5_K_M  (54 GB)"
        echo ""
        echo "Gewählte Modelle:"
        echo "  LLM:       $LLM_MODEL"
        echo "  Embedding: $EMBEDDING_MODEL"
        echo ""
        echo "Umgebungsvariablen (zum Überschreiben):"
        echo "  OLLAMA_MODEL           Alternatives LLM-Modell"
        echo "  EMBEDDING_OLLAMA_MODEL Alternatives Embedding-Modell"
        echo "  OLLAMA_BASE_URL        Ollama Server URL"
        ;;

    *)
        echo ""
        echo "Hardware:  $GPU_TYPE (${MEMORY_GB} GB)"
        echo "LLM:       $LLM_MODEL"
        echo "Embedding: $EMBEDDING_MODEL"
        echo ""
        echo "Docker:    docker compose $COMPOSE_FILES up -d"
        echo ""
        echo "Nächste Schritte:"
        echo "  ./setup.sh --start       # Alles starten"
        echo "  ./setup.sh --setup-macos # Apple Silicon Setup"
        echo "  ./setup.sh --help        # Mehr Optionen"
        ;;
esac

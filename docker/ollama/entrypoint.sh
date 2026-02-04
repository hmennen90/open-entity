#!/bin/bash
# OpenEntity Ollama Entrypoint
# Erkennt GPU/VRAM und installiert passende Modelle automatisch

set -e

# Standardmodelle
EMBEDDING_MODEL="${EMBEDDING_OLLAMA_MODEL:-nomic-embed-text}"
LLM_MODEL="${OLLAMA_MODEL:-}"

# GPU und Speicher erkennen
detect_hardware() {
    local gpu_type="none"
    local memory_gb=0

    # NVIDIA GPU prüfen
    if command -v nvidia-smi &> /dev/null; then
        if nvidia-smi &> /dev/null 2>&1; then
            gpu_type="nvidia"
            # VRAM in MB ermitteln
            local vram_mb=$(nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>/dev/null | head -1)
            if [ -n "$vram_mb" ]; then
                memory_gb=$((vram_mb / 1024))
            else
                memory_gb=8  # Fallback
            fi
            echo "✅ NVIDIA GPU erkannt (${memory_gb} GB VRAM)"
        fi
    fi

    # AMD GPU prüfen (ROCm)
    if [ "$gpu_type" = "none" ] && command -v rocm-smi &> /dev/null; then
        gpu_type="amd"
        local vram_mb=$(rocm-smi --showmeminfo vram 2>/dev/null | grep "Total" | awk '{print $3}' | head -1)
        if [ -n "$vram_mb" ]; then
            memory_gb=$((vram_mb / 1024))
        else
            memory_gb=8  # Fallback
        fi
        echo "✅ AMD GPU erkannt (${memory_gb} GB VRAM)"
    fi

    # CPU-only Fallback
    if [ "$gpu_type" = "none" ]; then
        # System RAM als Fallback
        if [ -f /proc/meminfo ]; then
            local mem_kb=$(grep MemTotal /proc/meminfo | awk '{print $2}')
            memory_gb=$((mem_kb / 1024 / 1024))
        else
            memory_gb=8  # Fallback
        fi
        echo "ℹ️  Keine GPU erkannt - CPU-Modus (${memory_gb} GB RAM)"
    fi

    # Globale Variablen setzen
    GPU_TYPE="$gpu_type"
    MEMORY_GB="$memory_gb"
}

# Modell basierend auf Speicher wählen
select_model_by_memory() {
    local mem_gb=$1

    if [ "$mem_gb" -lt 6 ]; then
        echo "qwen2.5:7b-instruct-q4_K_M"      # 4.7 GB
    elif [ "$mem_gb" -lt 10 ]; then
        echo "qwen2.5:7b-instruct-q5_K_M"      # 5.4 GB
    elif [ "$mem_gb" -lt 14 ]; then
        echo "qwen2.5:14b-instruct-q4_K_M"     # 9 GB
    elif [ "$mem_gb" -lt 22 ]; then
        echo "qwen2.5:14b-instruct-q5_K_M"     # 11 GB
    elif [ "$mem_gb" -lt 26 ]; then
        echo "qwen2.5:32b-instruct-q4_K_M"     # 20 GB
    elif [ "$mem_gb" -lt 50 ]; then
        echo "qwen2.5:32b-instruct-q5_K_M"     # 23 GB
    elif [ "$mem_gb" -lt 58 ]; then
        echo "qwen2.5:72b-instruct-q4_K_M"     # 47 GB
    else
        echo "qwen2.5:72b-instruct-q5_K_M"     # 54 GB
    fi
}

# Modelle pullen
pull_models() {
    echo ""
    echo "📦 Prüfe/Lade Ollama-Modelle..."
    echo "   LLM: $LLM_MODEL"
    echo "   Embedding: $EMBEDDING_MODEL"
    echo ""

    # LLM-Modell
    if ollama list 2>/dev/null | grep -q "^${LLM_MODEL}"; then
        echo "   ✅ $LLM_MODEL bereits vorhanden"
    else
        echo "   ⬇️  Lade $LLM_MODEL..."
        ollama pull "$LLM_MODEL"
        echo "   ✅ $LLM_MODEL geladen"
    fi

    # Embedding-Modell
    if ollama list 2>/dev/null | grep -q "^${EMBEDDING_MODEL}"; then
        echo "   ✅ $EMBEDDING_MODEL bereits vorhanden"
    else
        echo "   ⬇️  Lade $EMBEDDING_MODEL..."
        ollama pull "$EMBEDDING_MODEL"
        echo "   ✅ $EMBEDDING_MODEL geladen"
    fi
}

# Hauptlogik
echo ""
echo "🚀 OpenEntity Ollama Setup"
echo "=========================="

# Hardware erkennen
detect_hardware

# Modell wählen wenn nicht explizit gesetzt
if [ -z "$LLM_MODEL" ]; then
    if [ "$GPU_TYPE" = "none" ]; then
        # CPU: Konservativere Modellwahl
        if [ "$MEMORY_GB" -lt 10 ]; then
            LLM_MODEL="qwen2.5:7b-instruct-q4_K_M"
        elif [ "$MEMORY_GB" -lt 16 ]; then
            LLM_MODEL="qwen2.5:7b-instruct-q5_K_M"
        else
            LLM_MODEL="qwen2.5:14b-instruct-q4_K_M"
        fi
    else
        LLM_MODEL=$(select_model_by_memory $MEMORY_GB)
    fi
fi

echo "   Gewähltes Modell: $LLM_MODEL"

# Ollama Server im Hintergrund starten
echo ""
echo "🔄 Starte Ollama Server..."
ollama serve &
OLLAMA_PID=$!

# Warten bis Server bereit ist
echo "   Warte auf Server..."
for i in {1..30}; do
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        echo "   ✅ Server bereit"
        break
    fi
    sleep 1
done

# Modelle pullen (im Hintergrund, damit Container schnell startet)
if [ "${OLLAMA_SKIP_PULL:-false}" != "true" ]; then
    pull_models &
fi

# Marker-Datei für Health-Check
touch /tmp/ollama-ready

echo ""
echo "✅ Ollama läuft"
echo "   Host: 0.0.0.0:11434"
echo "   GPU:  $GPU_TYPE"
echo "   VRAM: ${MEMORY_GB} GB"
echo "   LLM:  $LLM_MODEL"
echo ""

# Auf Ollama-Prozess warten
wait $OLLAMA_PID

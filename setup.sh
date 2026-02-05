#!/bin/bash
# OpenEntity Setup Script for Linux and macOS
# Installs Ollama natively for GPU acceleration

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo ""
echo -e "${MAGENTA}OpenEntity Setup${NC}"
echo -e "${MAGENTA}================${NC}"
echo ""

# Detect OS
OS="unknown"
if [[ "$(uname)" == "Darwin" ]]; then
    OS="macos"
elif [[ -f /etc/debian_version ]]; then
    OS="debian"
elif [[ -f /etc/redhat-release ]] || [[ -f /etc/fedora-release ]]; then
    OS="rhel"
elif [[ -f /etc/arch-release ]]; then
    OS="arch"
else
    OS="linux"
fi

# Detect GPU and Memory
GPU_TYPE="cpu"
TOTAL_MEM_GB=8

# Allow override for CI testing
if [[ -n "${TEST_MEMORY_GB:-}" ]]; then
    TOTAL_MEM_GB=$TEST_MEMORY_GB
    echo -e "${CYAN}Platform: CI Test Mode${NC}"
    echo -e "         Simulated ${TOTAL_MEM_GB} GB RAM"
elif [[ "$OS" == "macos" ]]; then
    TOTAL_MEM_GB=$(($(sysctl -n hw.memsize 2>/dev/null) / 1024 / 1024 / 1024))
    if [[ "$(uname -m)" == "arm64" ]]; then
        GPU_TYPE="apple"
        echo -e "${GREEN}Platform: macOS Apple Silicon${NC}"
        echo -e "         ${TOTAL_MEM_GB} GB Unified Memory (GPU accelerated)"
    else
        echo -e "${YELLOW}Platform: macOS Intel${NC}"
        echo -e "         ${TOTAL_MEM_GB} GB RAM (CPU only)"
    fi
else
    TOTAL_MEM_GB=$(($(grep MemTotal /proc/meminfo | awk '{print $2}') / 1024 / 1024))

    # Check for NVIDIA GPU
    if command -v nvidia-smi &> /dev/null && nvidia-smi &> /dev/null; then
        GPU_TYPE="nvidia"
        GPU_INFO=$(nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>/dev/null | head -1)
        VRAM_MB=$(nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits 2>/dev/null | head -1)
        TOTAL_MEM_GB=$((VRAM_MB / 1024))
        echo -e "${GREEN}Platform: Linux with NVIDIA GPU${NC}"
        echo -e "         $GPU_INFO"
    # Check for AMD GPU (ROCm)
    elif command -v rocm-smi &> /dev/null; then
        GPU_TYPE="amd"
        echo -e "${GREEN}Platform: Linux with AMD GPU (ROCm)${NC}"
    else
        echo -e "${YELLOW}Platform: Linux (CPU only)${NC}"
        echo -e "         ${TOTAL_MEM_GB} GB RAM"
    fi
fi

# Select model based on available memory
select_model() {
    local mem=$1
    if (( mem < 6 )); then
        echo "qwen2.5:3b"
    elif (( mem < 10 )); then
        echo "qwen2.5:7b"
    elif (( mem < 18 )); then
        echo "qwen2.5:14b"
    elif (( mem < 28 )); then
        echo "qwen2.5:14b"
    elif (( mem < 48 )); then
        echo "qwen2.5:32b"
    else
        echo "qwen2.5:72b"
    fi
}

RECOMMENDED_MODEL=$(select_model $TOTAL_MEM_GB)
echo -e "         Recommended model: ${CYAN}${RECOMMENDED_MODEL}${NC}"
echo ""

# Install Ollama
install_ollama() {
    echo -e "${BLUE}Installing Ollama...${NC}"

    if [[ "$OS" == "macos" ]]; then
        if command -v brew &> /dev/null; then
            brew install ollama
        else
            echo -e "${YELLOW}Homebrew not found. Installing Ollama via official installer...${NC}"
            curl -fsSL https://ollama.ai/install.sh | sh
        fi
    elif [[ "$OS" == "debian" ]] || [[ "$OS" == "rhel" ]] || [[ "$OS" == "arch" ]] || [[ "$OS" == "linux" ]]; then
        # Official Ollama installer works on all Linux
        curl -fsSL https://ollama.ai/install.sh | sh
    fi

    echo -e "${GREEN}Ollama installed successfully${NC}"
}

# Start Ollama service
start_ollama() {
    if curl -s "http://localhost:11434/api/tags" > /dev/null 2>&1; then
        echo -e "${GREEN}Ollama is already running${NC}"
        return 0
    fi

    echo -e "${BLUE}Starting Ollama...${NC}"

    if [[ "$OS" == "macos" ]]; then
        # On macOS, start as background process
        ollama serve > /dev/null 2>&1 &
        sleep 3
    else
        # On Linux, use systemd if available
        if command -v systemctl &> /dev/null; then
            sudo systemctl enable ollama 2>/dev/null || true
            sudo systemctl start ollama
        else
            ollama serve > /dev/null 2>&1 &
        fi
        sleep 3
    fi

    # Wait for Ollama to be ready
    local retries=30
    while ! curl -s "http://localhost:11434/api/tags" > /dev/null 2>&1; do
        retries=$((retries - 1))
        if (( retries <= 0 )); then
            echo -e "${RED}Failed to start Ollama${NC}"
            return 1
        fi
        sleep 1
    done

    echo -e "${GREEN}Ollama is running${NC}"
}

# Pull required models
pull_models() {
    local model=${1:-$RECOMMENDED_MODEL}

    echo -e "${BLUE}Pulling LLM model: ${model}...${NC}"
    ollama pull "$model"

    echo -e "${BLUE}Pulling embedding model: nomic-embed-text...${NC}"
    ollama pull nomic-embed-text

    echo -e "${GREEN}Models ready${NC}"
}

# Configure .env file
configure_env() {
    local model=${1:-$RECOMMENDED_MODEL}

    if [[ ! -f .env ]]; then
        if [[ -f .env.example ]]; then
            cp .env.example .env
            echo -e "${GREEN}Created .env from .env.example${NC}"
        else
            echo -e "${RED}.env.example not found${NC}"
            return 1
        fi
    fi

    # Determine Ollama URL for Docker
    local ollama_url="http://host.docker.internal:11434"
    if [[ "$OS" != "macos" ]]; then
        # On Linux, host.docker.internal may not work, use host network IP
        local host_ip=$(ip route get 1 2>/dev/null | awk '{print $7}' | head -1)
        if [[ -n "$host_ip" ]]; then
            ollama_url="http://${host_ip}:11434"
        fi
    fi

    # Update .env settings
    update_env_var() {
        local key=$1
        local value=$2
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i.bak "s|^${key}=.*|${key}=${value}|" .env
        elif grep -q "^#${key}=" .env 2>/dev/null; then
            sed -i.bak "s|^#${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    }

    update_env_var "OLLAMA_BASE_URL" "$ollama_url"
    update_env_var "OLLAMA_MODEL" "$model"
    update_env_var "OLLAMA_PORT" "11435"  # Use different port to avoid conflict with native Ollama
    update_env_var "COMPOSE_FILE" "docker-compose.yml:docker-compose.native-ollama.yml"

    # Clean up backup files
    rm -f .env.bak

    echo -e "${GREEN}Configured .env for native Ollama${NC}"
    echo -e "         OLLAMA_BASE_URL=${ollama_url}"
    echo -e "         OLLAMA_MODEL=${model}"
}

# Start Docker containers
start_docker() {
    echo -e "${BLUE}Starting Docker containers...${NC}"

    # Use native-ollama override
    docker compose -f docker-compose.yml -f docker-compose.native-ollama.yml up -d

    echo ""
    echo -e "${GREEN}OpenEntity is running!${NC}"
    echo ""
    echo -e "Frontend: ${CYAN}http://localhost:8080${NC}"
    echo -e "API:      ${CYAN}http://localhost:8080/api/v1${NC}"
}

# Show help
show_help() {
    echo "Usage: ./setup.sh [command] [options]"
    echo ""
    echo "Commands:"
    echo "  install          Install Ollama and pull models (recommended first run)"
    echo "  start            Start Ollama and Docker containers"
    echo "  stop             Stop Docker containers"
    echo "  status           Show status of all services"
    echo "  pull-models      Pull/update LLM models"
    echo "  help             Show this help"
    echo ""
    echo "Options:"
    echo "  --model NAME     Use specific model (default: auto-selected based on RAM)"
    echo "  --docker-only    Start only Docker containers (Ollama must be running)"
    echo ""
    echo "Examples:"
    echo "  ./setup.sh install              # First-time setup"
    echo "  ./setup.sh start                # Start everything"
    echo "  ./setup.sh start --model qwen2.5:7b"
    echo ""
    echo "Recommended models by memory:"
    echo "  < 6 GB:   qwen2.5:3b"
    echo "  6-10 GB:  qwen2.5:7b"
    echo "  10-18 GB: qwen2.5:14b"
    echo "  18-28 GB: qwen2.5:14b (full precision)"
    echo "  28-48 GB: qwen2.5:32b"
    echo "  > 48 GB:  qwen2.5:72b"
}

# Parse command line arguments
COMMAND="${1:-}"
MODEL=""
DOCKER_ONLY=false

shift || true
while [[ $# -gt 0 ]]; do
    case $1 in
        --model)
            MODEL="$2"
            shift 2
            ;;
        --docker-only)
            DOCKER_ONLY=true
            shift
            ;;
        *)
            shift
            ;;
    esac
done

# Use recommended model if not specified
MODEL="${MODEL:-$RECOMMENDED_MODEL}"

# Execute command
case "$COMMAND" in
    install)
        if ! command -v ollama &> /dev/null; then
            install_ollama
        else
            echo -e "${GREEN}Ollama is already installed${NC}"
        fi
        start_ollama
        pull_models "$MODEL"
        configure_env "$MODEL"
        echo ""
        echo -e "${GREEN}Installation complete!${NC}"
        echo -e "Run ${CYAN}./setup.sh start${NC} to start OpenEntity"
        ;;

    start)
        if [[ "$DOCKER_ONLY" != true ]]; then
            if ! command -v ollama &> /dev/null; then
                echo -e "${RED}Ollama is not installed. Run './setup.sh install' first.${NC}"
                exit 1
            fi
            start_ollama
        fi
        configure_env "$MODEL"
        start_docker
        ;;

    stop)
        echo -e "${BLUE}Stopping Docker containers...${NC}"
        docker compose down
        echo -e "${GREEN}Stopped${NC}"
        ;;

    status)
        echo -e "${BLUE}Ollama:${NC}"
        if curl -s "http://localhost:11434/api/tags" > /dev/null 2>&1; then
            echo -e "  Status: ${GREEN}Running${NC}"
            echo "  Models:"
            ollama list 2>/dev/null | sed 's/^/    /'
        else
            echo -e "  Status: ${RED}Not running${NC}"
        fi
        echo ""
        echo -e "${BLUE}Docker:${NC}"
        docker compose ps 2>/dev/null | sed 's/^/  /'
        ;;

    pull-models)
        start_ollama
        pull_models "$MODEL"
        ;;

    help|--help|-h)
        show_help
        ;;

    "")
        echo "System detected: ${OS}, ${GPU_TYPE} GPU, ${TOTAL_MEM_GB} GB memory"
        echo ""
        show_help
        ;;

    *)
        echo -e "${RED}Unknown command: ${COMMAND}${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac

echo ""

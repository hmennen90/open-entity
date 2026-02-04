FROM ollama/ollama:latest

# curl für Health-Checks installieren
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

# Entrypoint-Script kopieren
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Ollama-Daten
VOLUME /root/.ollama

# Port
EXPOSE 11434

# Umgebungsvariablen
ENV OLLAMA_HOST=0.0.0.0
ENV OLLAMA_SKIP_PULL=false

ENTRYPOINT ["/entrypoint.sh"]

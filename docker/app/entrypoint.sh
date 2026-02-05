#!/bin/bash
set -e

cd /var/www

# CONTAINER_ROLE bestimmt das Verhalten:
# - "app": Installiert Dependencies, führt Migrationen aus
# - "worker": Wartet nur auf Dependencies
ROLE="${CONTAINER_ROLE:-app}"

if [ "$ROLE" = "app" ]; then
    # === APP CONTAINER: Installiert alles ===

    # Storage-Verzeichnisse erstellen (benötigt für Laravel Caching während composer install)
    # Ignore errors - directories might already exist or permissions handled by volume mounts
    mkdir -p storage/framework/{sessions,views,cache} 2>/dev/null || true
    mkdir -p storage/logs 2>/dev/null || true
    mkdir -p storage/entity/{mind,memory,social,goals,tools} 2>/dev/null || true
    mkdir -p bootstrap/cache 2>/dev/null || true

    # .env erstellen BEVOR composer install (Laravel braucht Broadcast-Config)
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            echo "📝 Creating .env from .env.example..."
            cp .env.example .env
        fi
    fi

    # Composer-Dependencies installieren wenn vendor/ fehlt
    if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
        echo "📦 Installing Composer dependencies..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    # Application Key generieren wenn noch nicht vorhanden
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "🔑 Generating application key..."
        php artisan key:generate --no-interaction
    fi

    # NPM-Dependencies installieren wenn node_modules/ fehlt
    if [ ! -d "node_modules" ]; then
        echo "📦 Installing NPM dependencies..."
        npm install
    fi

    # Frontend bauen wenn public/build/ fehlt
    if [ ! -d "public/build" ] || [ ! -f "public/build/manifest.json" ]; then
        echo "🔨 Building frontend..."
        npm run build
    fi

else
    # === WORKER CONTAINER: Wartet auf Dependencies ===

    echo "⏳ Waiting for vendor directory..."
    while [ ! -f "vendor/autoload.php" ]; do
        sleep 2
    done
    echo "✅ Vendor ready"

    echo "⏳ Waiting for .env file..."
    while [ ! -f ".env" ]; do
        sleep 2
    done
    echo "✅ Environment ready"

    echo "⏳ Waiting for application key..."
    while ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; do
        sleep 2
    done
    echo "✅ Application key ready"
fi

# Storage-Verzeichnisse sicherstellen (für alle Container)
mkdir -p storage/framework/{sessions,views,cache} 2>/dev/null || true
mkdir -p storage/logs 2>/dev/null || true
mkdir -p bootstrap/cache 2>/dev/null || true

# Warte auf MySQL wenn DB_HOST gesetzt ist
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for MySQL..."
    while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --skip-ssl --silent 2>/dev/null; do
        sleep 2
    done
    echo "✅ MySQL is ready"

    # Migrationen ausführen (nur wenn Tabellen fehlen)
    if ! php artisan migrate:status --no-interaction 2>/dev/null | grep -q "Ran"; then
        echo "🔄 Running migrations..."
        php artisan migrate --force --no-interaction

        # Warte auf Ollama bevor Seeder läuft (für Modellerkennung)
        OLLAMA_URL="${OLLAMA_BASE_URL:-http://ollama:11434}"
        echo "⏳ Waiting for Ollama..."
        for i in {1..60}; do
            if curl -s "$OLLAMA_URL/api/tags" > /dev/null 2>&1; then
                echo "✅ Ollama is ready"
                break
            fi
            sleep 2
        done

        # Seeder ausführen bei Erstinstallation
        echo "🌱 Running seeders..."
        php artisan db:seed --force --no-interaction
    fi
fi

# Cache leeren bei erstem Start
if [ ! -f "storage/.initialized" ]; then
    echo "🔧 Initial setup..."
    php artisan config:clear
    php artisan cache:clear
    php artisan view:clear
    touch storage/.initialized
fi

# Übergebenen Command ausführen
exec "$@"

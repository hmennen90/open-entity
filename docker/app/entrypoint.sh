#!/bin/bash
set -e

cd /var/www

# Composer-Dependencies installieren wenn vendor/ fehlt
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# NPM-Dependencies installieren wenn node_modules/ fehlt
if [ ! -d "node_modules" ]; then
    echo "📦 Installing NPM dependencies..."
    npm install
fi

# .env erstellen wenn nicht vorhanden
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "📝 Creating .env from .env.example..."
        cp .env.example .env
        php artisan key:generate --no-interaction
    fi
fi

# Storage-Verzeichnisse erstellen und Berechtigungen setzen
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p storage/entity/{mind,memory,social,goals,tools}
mkdir -p bootstrap/cache

# Warte auf MySQL wenn DB_HOST gesetzt ist
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for MySQL..."
    while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
        sleep 2
    done
    echo "✅ MySQL is ready"

    # Migrationen ausführen (nur wenn Tabellen fehlen)
    if ! php artisan migrate:status --no-interaction 2>/dev/null | grep -q "Ran"; then
        echo "🔄 Running migrations..."
        php artisan migrate --force --no-interaction
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

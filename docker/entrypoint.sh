#!/bin/bash
set -e

# Wait for MySQL to be ready (belt and suspenders with healthcheck)
echo "Waiting for database..."
while ! php -r "try { new PDO('mysql:host=db;port=3306;dbname=krayin_crm', 'krayin', 'secret'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 2
done
echo "Database is ready."

# Install composer dependencies if vendor doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Set up .env if not present
if [ ! -f ".env" ]; then
    echo "Setting up .env file..."
    cp .env.example .env

    # Configure for Docker
    sed -i "s|APP_URL=.*|APP_URL=http://localhost:8190|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=db|" .env
    sed -i "s|DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=krayin_crm|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=krayin|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=secret|" .env
    sed -i "s|REDIS_HOST=.*|REDIS_HOST=redis|" .env
    sed -i "s|CACHE_DRIVER=.*|CACHE_DRIVER=redis|" .env
    sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" .env
    sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=redis|" .env
    sed -i "s|MAIL_HOST=.*|MAIL_HOST=mailpit|" .env
    sed -i "s|MAIL_PORT=.*|MAIL_PORT=1025|" .env
    sed -i "s|APP_TIMEZONE=.*|APP_TIMEZONE=UTC|" .env

    php artisan key:generate
fi

# Run migrations if the database is empty
TABLE_COUNT=$(php -r "try { \$pdo = new PDO('mysql:host=db;port=3306;dbname=krayin_crm', 'krayin', 'secret'); \$r = \$pdo->query('SHOW TABLES'); echo \$r->rowCount(); } catch(Exception \$e) { echo '0'; }")

if [ "$TABLE_COUNT" = "0" ]; then
    echo "Running initial setup..."
    php artisan krayin-crm:install --no-interaction || true

    # If install command doesn't work headlessly, do it manually
    if [ "$TABLE_COUNT" = "0" ]; then
        php artisan migrate --force 2>/dev/null || true
        php artisan db:seed --force 2>/dev/null || true
    fi
fi

# Build frontend assets
if [ ! -d "public/build" ]; then
    echo "Building frontend assets..."
    npm install 2>/dev/null || true
    npm run build 2>/dev/null || true
fi

# Set permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

exec "$@"

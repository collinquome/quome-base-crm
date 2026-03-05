#!/bin/bash
set -e

echo "Starting Quome CRM (production) setup..."

cd /var/www/html

# Railway support: parse MYSQL_URL if provided (Railway MySQL plugin)
if [ -n "$MYSQL_URL" ] && [ -z "$DB_HOST" ]; then
    echo "Parsing MYSQL_URL from Railway..."
    DB_HOST=$(echo "$MYSQL_URL" | sed -n 's|.*@\([^:]*\):.*|\1|p')
    DB_PORT=$(echo "$MYSQL_URL" | sed -n 's|.*:\([0-9]*\)/.*|\1|p')
    DB_DATABASE=$(echo "$MYSQL_URL" | sed -n 's|.*/\([^?]*\).*|\1|p')
    DB_USERNAME=$(echo "$MYSQL_URL" | sed -n 's|mysql://\([^:]*\):.*|\1|p')
    DB_PASSWORD=$(echo "$MYSQL_URL" | sed -n 's|mysql://[^:]*:\([^@]*\)@.*|\1|p')
    export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
fi

# Railway also provides MYSQLHOST etc. as individual vars
if [ -n "$MYSQLHOST" ] && [ -z "$DB_HOST" ]; then
    DB_HOST="$MYSQLHOST"
    DB_PORT="${MYSQLPORT:-3306}"
    DB_DATABASE="${MYSQLDATABASE:-railway}"
    DB_USERNAME="${MYSQLUSER:-root}"
    DB_PASSWORD="${MYSQLPASSWORD:-}"
    export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
fi

# Remove default nginx site config (may survive Docker layer caching)
rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default

# Railway support: update nginx to listen on $PORT if set
if [ -n "$PORT" ]; then
    echo "Railway PORT detected: $PORT — updating nginx..."
    sed -i "s/listen 80 default_server;/listen $PORT default_server;/" /etc/nginx/conf.d/app.conf
fi

# Ensure storage directory structure exists (volume mount may be empty)
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Clear cached bootstrap files that may reference dev-only packages
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

# Compute APP_URL: explicit > Railway domain > fallback
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
fi
APP_URL="${APP_URL:-http://localhost}"

# Generate .env from environment variables
cat > .env <<ENVEOF
APP_NAME="${APP_NAME:-Quome CRM}"
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL}
APP_TIMEZONE=${APP_TIMEZONE:-UTC}
APP_LOCALE=${APP_LOCALE:-en}
APP_CURRENCY=${APP_CURRENCY:-USD}

LOG_CHANNEL=stack
LOG_LEVEL=${LOG_LEVEL:-warning}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-quome_crm}
DB_USERNAME=${DB_USERNAME:-quome}
DB_PASSWORD=${DB_PASSWORD:-secret}
DB_PREFIX=

BROADCAST_DRIVER=${BROADCAST_DRIVER:-log}
CACHE_DRIVER=${CACHE_DRIVER:-file}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
SESSION_DRIVER=${SESSION_DRIVER:-file}
SESSION_LIFETIME=120

REDIS_HOST=${REDIS_HOST:-127.0.0.1}
REDIS_PASSWORD=${REDIS_PASSWORD:-null}
REDIS_PORT=${REDIS_PORT:-6379}

MAIL_MAILER=${MAIL_MAILER:-smtp}
MAIL_HOST=${MAIL_HOST:-localhost}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME=${MAIL_USERNAME:-null}
MAIL_PASSWORD=${MAIL_PASSWORD:-null}
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-null}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-noreply@example.com}
MAIL_FROM_NAME="${MAIL_FROM_NAME:-Quome CRM}"
ENVEOF

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Wait for MySQL
echo "Waiting for database..."
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if php -r "try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}'); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "Database is ready."
        break
    fi
    TRIES=$((TRIES + 1))
    echo "Waiting for database... attempt $TRIES/$MAX_TRIES"
    sleep 2
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo "ERROR: Could not connect to database after $MAX_TRIES attempts"
    exit 1
fi

# Run migrations
HAS_USERS=$(php -r "try { \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}'); \$r = \$pdo->query(\"SELECT COUNT(*) FROM users\"); echo \$r->fetchColumn(); } catch(Exception \$e) { echo 'no'; }" 2>/dev/null)

if [ "$HAS_USERS" = "no" ] || [ -z "$HAS_USERS" ]; then
    echo "Running initial database setup..."
    php artisan migrate --force 2>&1 || true
    php artisan db:seed --force 2>&1 || true

    php artisan tinker --execute="
        \$user = \Webkul\User\Models\User::first();
        if (\$user) {
            \$user->password = bcrypt('admin123');
            \$user->save();
        }
    " 2>/dev/null || true

    echo "Database setup complete. Default login: admin@example.com / admin123"
else
    echo "Running any new migrations..."
    php artisan migrate --force 2>&1 || true

    # Fix leads with NULL user_id (assign to first admin user)
    php artisan tinker --execute="
        \$admin = \Webkul\User\Models\User::first();
        if (\$admin) {
            \$count = \DB::table('leads')->whereNull('user_id')->update(['user_id' => \$admin->id]);
            echo \"Fixed {\$count} leads with NULL user_id.\";
        }
    " 2>/dev/null || true
fi

# Apply demo brand if brand.json exists and white-label not yet configured
if [ -f "public/demo-brand/brand.json" ]; then
    php artisan brand:apply public/demo-brand/brand.json 2>/dev/null || \
    php artisan tinker --execute="
        if (\Schema::hasTable('white_label_settings')) {
            \DB::table('white_label_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'app_name' => 'NovaCRM',
                    'primary_color' => '#0891B2',
                    'secondary_color' => '#0E7490',
                    'accent_color' => '#F59E0B',
                    'email_sender_name' => 'NovaCRM',
                    'logo_url' => '/demo-brand/logo.png',
                    'logo_dark_url' => '/demo-brand/logo-dark.png',
                    'updated_at' => now(),
                ]
            );
            echo 'White label brand applied with logo.';
        }
    " 2>/dev/null || true
else
    php artisan tinker --execute="
        if (\Schema::hasTable('white_label_settings') && \DB::table('white_label_settings')->count() === 0) {
            \DB::table('white_label_settings')->insert([
                'app_name' => 'CRM',
                'primary_color' => '#1E40AF',
                'secondary_color' => '#7C3AED',
                'accent_color' => '#F59E0B',
                'email_sender_name' => 'CRM',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo 'White label defaults seeded.';
        }
    " 2>/dev/null || true
fi

# Storage link and permissions
php artisan storage:link 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Optimize for production
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

echo "============================================"
echo "  Quome CRM (prod) is ready!"
echo "  URL: ${APP_URL:-http://localhost}"
echo "  Login: admin@example.com / admin123"
echo "============================================"

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

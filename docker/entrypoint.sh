#!/bin/bash

echo "Starting Krayin CRM setup..."

# 1. Install composer dependencies FIRST
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# 2. Set up .env if not present
if [ ! -f ".env" ]; then
    echo "Setting up .env file..."
    cp .env.example .env
fi

# Always ensure Docker service hostnames are correct (fixes issues if .env was
# overwritten by a different entrypoint, e.g. the production Dockerfile)
echo "Ensuring Docker service hostnames in .env..."
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

# Generate key if missing
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# 3. Wait for MySQL to be ready
echo "Waiting for database..."
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if php -r "try { new PDO('mysql:host=db;port=3306;dbname=krayin_crm', 'krayin', 'secret'); exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "Database is ready."
        break
    fi
    TRIES=$((TRIES + 1))
    sleep 2
done

# 4. Run migrations if the users table doesn't exist
HAS_USERS=$(php -r "try { \$pdo = new PDO('mysql:host=db;port=3306;dbname=krayin_crm', 'krayin', 'secret'); \$r = \$pdo->query(\"SELECT COUNT(*) FROM users\"); echo \$r->fetchColumn(); } catch(Exception \$e) { echo 'no'; }" 2>/dev/null)

if [ "$HAS_USERS" = "no" ] || [ -z "$HAS_USERS" ]; then
    echo "Running initial database setup..."
    php artisan migrate --force 2>&1 || true
    php artisan db:seed --force 2>&1 || true

    # Set admin password to known default for development
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
fi

# Seed white-label defaults if not present
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

# 5. Build frontend assets if not built
if [ ! -d "public/build" ]; then
    echo "Building frontend assets..."
    npm install 2>&1 || true
    npm run build 2>&1 || true
fi

# 6. Storage link and permissions
php artisan storage:link 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 7. Clear caches for fresh start
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo "============================================"
echo "  Krayin CRM is ready!"
echo "  Web:     http://localhost:8190"
echo "  Mailpit: http://localhost:8191"
echo "  Login:   admin@example.com / admin123"
echo "============================================"
exec "$@"

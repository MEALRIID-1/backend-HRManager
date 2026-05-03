#!/bin/bash
set -e

# Créer le fichier .env s'il n'existe pas
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
fi

# Générer la clé applicative si elle est vide
if grep -q "^APP_KEY=$" /var/www/.env; then
    php artisan key:generate --force
fi

# Attendre que MySQL soit prêt
echo "Attente de la connexion MySQL..."
until php -r "
    \$host = getenv('DB_HOST') ?: '127.0.0.1';
    \$port = getenv('DB_PORT') ?: '3306';
    \$conn = @fsockopen(\$host, \$port, \$errno, \$errstr, 3);
    if (\$conn) { fclose(\$conn); exit(0); } exit(1);
"; do
    echo "MySQL non disponible, nouvelle tentative dans 3s..."
    sleep 3
done
echo "MySQL disponible."

# Appliquer les migrations et les seeders
php artisan migrate --force
php artisan db:seed --force

# Optimiser le cache en production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Corriger les permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

exec php artisan serve --host=0.0.0.0 --port=8000

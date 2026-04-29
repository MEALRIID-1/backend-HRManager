#!/bin/sh
set -e

# Attendre que la base de données soit prête
if [ -n "$DB_HOST" ]; then
    echo "Attente de la base de données..."
    until nc -z -v -w30 "$DB_HOST" "${DB_PORT:-3306}"
    do
        echo "En attente de la connexion à la base de données..."
        sleep 5
    done
    echo "Base de données prête!"
fi

# Créer les répertoires de stockage s'ils n'existent pas
mkdir -p /var/www/storage/app/public
mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Définir les permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Générer la clé de l'application si elle n'existe pas
if [ -z "$APP_KEY" ]; then
    echo "Génération de la clé de l'application..."
    php /var/www/artisan key:generate --force
fi

# Publier les migrations des packages si elles n'existent pas
if [ ! -f "/var/www/database/migrations/2024_01_01_000000_create_permission_tables.php" ]; then
    echo "Publication des migrations de permission..."
    php /var/www/artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations" --force 2>/dev/null || true
fi

# Exécuter les migrations si demandé
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Exécution des migrations..."
    php /var/www/artisan migrate --force
fi

# Exécuter les seeders si demandé
if [ "$RUN_SEEDERS" = "true" ]; then
    echo "Exécution des seeders..."
    php /var/www/artisan db:seed --force
fi

# Créer le lien symbolique pour le stockage
php /var/www/artisan storage:link --force 2>/dev/null || true

# Optimiser Laravel en production (désactivé temporairement à cause de Telescope)
# if [ "$APP_ENV" = "production" ]; then
#     php /var/www/artisan config:cache
#     php /var/www/artisan route:cache
#     php /var/www/artisan view:cache
# fi

echo "Démarrage des services..."
exec "$@"

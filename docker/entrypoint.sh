#!/bin/bash

set -e

echo "🚀 Démarrage de HRManager..."

# 1. Composer install
echo "📦 Installation des dépendances Composer..."
composer install --no-interaction --optimize-autoloader

# 2. Generate APP_KEY if empty
if [ -z "$APP_KEY" ]; then
    echo "🔑 Génération de la clé d'application..."
    php artisan key:generate --force
fi

# 3. Wait for MySQL to be ready
echo "⏳ Attente de MySQL..."
until php -r "new PDO('mysql:host=$DB_HOST;port=$DB_PORT', '$DB_USERNAME', '$DB_PASSWORD');" 2>/dev/null; do
    echo "   MySQL n'est pas encore prêt..."
    sleep 2
done
echo "✅ MySQL est prêt !"

# 4. Run migrations
echo "🗄️  Exécution des migrations..."
php artisan migrate --force

# 5. Run seeders if no users exist (first time)
echo "🌱 Vérification des données initiales..."
USER_COUNT=$(php -r "
try {
    \$pdo = new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD');
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM users');
    echo \$stmt->fetchColumn();
} catch (Exception \$e) {
    echo 0;
}
")

if [ "$USER_COUNT" -eq "0" ]; then
    echo "   Aucun utilisateur trouvé - Seeding initial..."
    php artisan db:seed --force
else
    echo "   Utilisateurs déjà présents ($USER_COUNT) - Seeding ignoré"
fi

# 6. Storage link
echo "🔗 Création du lien symbolique storage..."
php artisan storage:link --force || true

# 7. Config cache
echo "⚡ Mise en cache de la configuration..."
php artisan config:cache

# 8. Route cache
echo "⚡ Mise en cache des routes..."
php artisan route:cache

# 9. View cache
echo "⚡ Mise en cache des vues..."
php artisan view:cache

# 10. Start queue worker for emails (background process)
echo "📧 Démarrage du worker de queue pour les emails..."
nohup php artisan queue:work redis --queue=emails --sleep=3 --tries=3 --timeout=60 > /var/www/html/storage/logs/queue-emails.log 2>&1 &
echo "   Queue worker emails démarré en arrière-plan"

echo ""
echo "✅ HRManager est prêt !"
echo ""
echo "🌐 Application disponible sur : http://localhost"
echo "📧 MailHog disponible sur : http://localhost:8025"
echo "📮 Queue worker emails démarré sur redis:emails"
echo ""

# Start php-fpm
exec php-fpm

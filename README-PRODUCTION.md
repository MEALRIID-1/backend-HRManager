# Guide de Déploiement Production HRManager

Configuration complète pour la mise en production sécurisée de HRManager.

## Table des Matières
1. [Prérequis](#prérequis)
2. [Configuration Serveur](#configuration-serveur)
3. [Checklist Sécurité](#checklist-sécurité)
4. [Déploiement](#déploiement)
5. [Monitoring](#monitoring)
6. [Maintenance](#maintenance)

---

## Prérequis

### Serveur Recommandé
- **OS**: Ubuntu 22.04 LTS
- **RAM**: 4GB minimum (8GB recommandé)
- **CPU**: 2 cores minimum
- **Disque**: 50GB SSD
- **Réseau**: 100 Mbps, ports 80/443 ouverts

### Logiciels
- Docker 24.0+
- Docker Compose 2.20+
- Git
- OpenSSL (pour Let's Encrypt)

### Services Externes
- SMTP (SendGrid, Mailgun, ou serveur interne)
- Redis (ou Redis Cloud)
- AWS S3 / MinIO (stockage fichiers)
- Slack Webhook (alertes)

---

## Configuration Serveur

### 1. Initialisation

```bash
# Mise à jour système
sudo apt update && sudo apt upgrade -y

# Installation Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Installation Docker Compose
sudo apt install docker-compose-plugin

# Création répertoires
sudo mkdir -p /opt/hrmanager
sudo mkdir -p /opt/hrmanager/backups
sudo mkdir -p /opt/hrmanager/logs
sudo chown -R $USER:$USER /opt/hrmanager

# Clonage repository
cd /opt/hrmanager
git clone https://github.com/your-org/hrmanager.git .
```

### 2. Configuration Environnement

Créer le fichier `.env.production`:

```bash
cp .env.example .env.production
nano .env.production
```

Variables obligatoires:
```env
# Application
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://hrmanager.votre-entreprise.com

# Database (utiliser des mots de passe forts)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=hrmanager
DB_USERNAME=hrmanager_user
DB_PASSWORD=VOTRE_MOT_DE_PASSE_FORT_32_CHARACTERS

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_STORE=redis
CACHE_PREFIX=hrmanager-production-cache

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxxxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hr@entreprise.com
MAIL_FROM_NAME="HRManager"

# Logging
LOG_CHANNEL=production
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/XXXX/XXXX/XXXX
LOG_LEVEL=warning

# AWS / S3
AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXXXX
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=hrmanager-documents-production

# Security
APP_FORCE_HTTPS=true
SANCTUM_STATEFUL_DOMAINS=hrmanager.votre-entreprise.com
```

**⚠️ IMPORTANT**: Générer APP_KEY:
```bash
php artisan key:generate --show
```

---

## Checklist Sécurité

### Avant Déploiement

- [ ] `APP_DEBUG=false` (NE JAMAIS mettre true en prod)
- [ ] `APP_KEY` généré et unique
- [ ] Mots de passe base de données forts (32+ caractères)
- [ ] Clés AWS rotées et permissions minimales
- [ ] Redis non exposé publiquement
- [ ] Firewall UFW configuré (ports 22, 80, 443 uniquement)
- [ ] SSL/TLS activé (Let's Encrypt)
- [ ] Headers de sécurité configurés (HSTS, CSP, X-Frame-Options)
- [ ] Rate limiting activé sur endpoints auth
- [ ] Sessions vers Redis (pas fichier)
- [ ] Logs externes configurés (Slack pour erreurs critiques)

### Permissions Fichiers

```bash
# Définir les permissions correctes
sudo chown -R www-data:www-data /opt/hrmanager
sudo chmod -R 755 /opt/hrmanager/storage
sudo chmod -R 755 /opt/hrmanager/bootstrap/cache
sudo chmod 644 /opt/hrmanager/.env.production
sudo chmod 644 /opt/hrmanager/docker/nginx/conf.d/*.conf
sudo chmod 644 /opt/hrmanager/docker/php/php-fpm.d/*.conf
```

### Protection des Données

- [ ] Backup automatique configuré (quotidien)
- [ ] Chiffrement des backups
- [ ] Rotation des logs (30 jours)
- [ ] Données sensibles chiffrées (IBAN, numéros sécu)
- [ ] Uploads limités aux types autorisés (PDF, images)
- [ ] Anti-virus sur uploads (si applicable)

---

## Déploiement

### Étape 1: Premier Démarrage

```bash
cd /opt/hrmanager

# Lancer les services
sudo docker-compose -f docker-compose.prod.yml up -d

# Vérifier santé
sudo docker-compose -f docker-compose.prod.yml ps

# Installer dépendances
sudo docker-compose -f docker-compose.prod.yml exec app composer install --no-dev

# Générer key si pas déjà fait
sudo docker-compose -f docker-compose.prod.yml exec app php artisan key:generate

# Migrations
sudo docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Seed initial (optionnel)
sudo docker-compose -f docker-compose.prod.yml exec app php artisan db:seed
```

### Étape 2: Optimisations

```bash
# Cache configuration
sudo docker-compose -f docker-compose.prod.yml exec app php artisan config:cache

# Cache routes
sudo docker-compose -f docker-compose.prod.yml exec app php artisan route:cache

# Cache views
sudo docker-compose -f docker-compose.prod.yml exec app php artisan view:cache

# Optimiser autoloader
sudo docker-compose -f docker-compose.prod.yml exec app composer dump-autoload --optimize
```

### Étape 3: Vérification

```bash
# Health check
./scripts/health-check.sh localhost 443

# Tester API
curl -f https://hrmanager.votre-entreprise.com/api/health

# Vérifier logs
docker-compose -f docker-compose.prod.yml logs -f app
```

---

## Monitoring

### Endpoints de Santé

| Endpoint | Description |
|----------|-------------|
| `/api/health` | Statut général |
| `/api/health/database` | Connexion MySQL |
| `/api/health/redis` | Connexion Redis |
| `/api/health/queue` | Statut workers |

### Logs

```bash
# Logs application
docker-compose -f docker-compose.prod.yml logs -f app

# Logs Nginx
docker-compose -f docker-compose.prod.yml logs -f nginx

# Logs workers
docker-compose -f docker-compose.prod.yml logs -f queue-worker

# Logs système
sudo tail -f /var/log/syslog | grep hrmanager
```

### Alertes

Configurer monitoring pour:
- CPU > 80%
- RAM > 85%
- Disque > 85%
- HTTP 5xx
- Queue size > 100 jobs
- Failed jobs

---

## Maintenance

### Commandes Courantes

```bash
# Redémarrer l'application
sudo docker-compose -f docker-compose.prod.yml restart app

# Redémarrer tous les workers
sudo docker-compose -f docker-compose.prod.yml restart queue-worker

# Mettre à jour l'image
sudo docker-compose -f docker-compose.prod.yml pull
sudo docker-compose -f docker-compose.prod.yml up -d

# Backup manuel
sudo docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u root -p hrmanager > backup_$(date +%Y%m%d).sql

# Nettoyer le cache
sudo docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
sudo docker-compose -f docker-compose.prod.yml exec app php artisan config:clear
```

### Rotation des Clés

**Recommandation**: Roter les clés tous les 90 jours.

```bash
# Rotation APP_KEY (attention: déconnexion tous les utilisateurs)
sudo docker-compose -f docker-compose.prod.yml exec app php artisan key:generate
sudo docker-compose -f docker-compose.prod.yml restart

# Rotation API tokens
sudo docker-compose -f docker-compose.prod.yml exec app php artisan passport:keys
```

### Mises à Jour Sécurité

```bash
# Mise à jour conteneurs
sudo docker-compose -f docker-compose.prod.yml pull
sudo docker-compose -f docker-compose.prod.yml up -d

# Mise à jour composer
sudo docker-compose -f docker-compose.prod.yml exec app composer update --no-dev

# Nettoyer vieilles images
docker system prune -f
```

---

## Troubleshooting

### Problèmes Courants

**502 Bad Gateway**
- Vérifier PHP-FPM: `docker-compose exec app php-fpm -t`
- Vérifier logs: `docker-compose logs php-fpm`

**Queue Worker Arrêté**
- Redémarrer: `docker-compose restart queue-worker`
- Vérifier Redis: `docker-compose exec redis redis-cli ping`

**Cache Corrompu**
```bash
sudo docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
sudo docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
```

**Permission Denied**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Contacts et Support

- **Admin Sys**: admin@entreprise.com
- **DevOps**: devops@entreprise.com
- **Urgence**: +33 X XX XX XX XX

---

## Références

- [Laravel Deployment](https://laravel.com/docs/11.x/deployment)
- [Docker Security](https://docs.docker.com/engine/security/)
- [Nginx Hardening](https://www.nginx.com/blog/do-s-and-don-ts-of-nginx-security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

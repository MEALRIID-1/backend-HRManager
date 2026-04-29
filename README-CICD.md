# CI/CD Configuration

Ce document décrit la configuration CI/CD pour HRManager Laravel.

## GitHub Actions Workflows

### CI (Continuous Integration)
Fichier: `.github/workflows/ci.yml`

Déclenché sur: push/PR vers `main` et `develop`

**Jobs:**
1. **Lint** - Vérification du code
   - PHP CS Fixer (convention PSR-12)
   - PHPStan niveau 6
   - Validation composer.json

2. **Test** - Tests automatiques
   - Matrix: PHP 8.3
   - Services: MySQL 8.0, Redis 7
   - Cache Composer
   - Coverage HTML + XML
   - Upload vers Codecov

3. **Build** - Construction Docker
   - Multi-stage build
   - Push vers Docker Hub (tag: SHA, branch, semver)
   - Cache layers avec BuildKit

### CD (Continuous Deployment)
Fichier: `.github/workflows/cd.yml`

**Deploy Staging** (auto sur push main)
- SSH vers VPS staging
- Docker pull + compose up
- Migrations --force
- Restart queues
- Health checks

**Deploy Production** (manuel via workflow_dispatch)
- Backup automatique avant déploiement
- Migrations avec rollback si échec
- Smoke tests post-déploiement
- Rollback automatique si failure

## Secrets GitHub Requis

### Docker Hub
| Secret | Description |
|--------|-------------|
| `DOCKER_USERNAME` | Nom d'utilisateur Docker Hub |
| `DOCKER_PASSWORD` | Token d'accès Docker Hub |

### Infrastructure
| Secret | Description |
|--------|-------------|
| `SSH_PRIVATE_KEY` | Clé SSH pour staging |
| `STAGING_HOST` | IP/nom de domaine staging |
| `STAGING_USER` | Utilisateur SSH staging |
| `SSH_PRIVATE_KEY_PRODUCTION` | Clé SSH pour production |
| `PRODUCTION_HOST` | IP/nom de domaine production |
| `PRODUCTION_USER` | Utilisateur SSH production |

### Base de données
| Secret | Description |
|--------|-------------|
| `DB_ROOT_PASSWORD` | Mot de passe root MySQL |

### Coverage
| Secret | Description |
|--------|-------------|
| `CODECOV_TOKEN` | Token Codecov (optionnel) |

## Architecture Docker

### Dockerfile (Multi-Stage)
```
Stage 1: builder
- PHP 8.3-cli-alpine
- Installation dépendances dev
- Composer install --no-dev

Stage 2: runner
- PHP 8.3-fpm-alpine
- Nginx + Supervisor
- OPcache configuré
- Health check intégré
```

### Docker Compose Production
Services:
- **app**: Application PHP-FPM
- **nginx**: Reverse proxy + SSL
- **mysql**: Base de données
- **redis**: Cache & sessions
- **queue-worker**: Workers Laravel
- **scheduler**: Cron tasks

## Commandes Utiles

### Local Development
```bash
# Build image
docker build -t hrmanager:latest .

# Run tests
docker-compose exec app php artisan test

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Production
```bash
# Déployer sur staging
./scripts/deploy.sh staging

# Vérifier santé
./scripts/health-check.sh localhost 80

# Logs
sudo docker-compose -f docker-compose.prod.yml logs -f app
```

## Configuration Serveur

### Prérequis VPS
- Docker 24.0+
- Docker Compose 2.20+
- SSH access configuré
- Ports 80, 443 ouverts

### Structure Répertoires
```
/opt/hrmanager/
├── docker-compose.prod.yml
├── .env.production
├── docker/
│   ├── nginx/
│   └── supervisor/
└── backups/
```

## Rollback

Le script `deploy.sh` inclut un rollback automatique:
1. Backup pre-déploiement
2. Migration échouée → rollback automatique
3. Health check échoué → rollback automatique
4. Restauration base de données si nécessaire

## Monitoring

### Health Endpoints
- `GET /api/health` - Statut général
- `GET /api/health/database` - Connexion MySQL
- `GET /api/health/redis` - Connexion Redis
- `GET /api/health/queue` - Statut queue workers

### Alertes
Intégrer des webhooks Slack/Teams dans le workflow CD pour notifications de déploiement.

## Variables d'Environnement

Créer `.env.production` sur le serveur avec:

```env
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=hrmanager
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=redis

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=...
AWS_ENDPOINT=...
```

## Performance

Optimisations incluses:
- OPcache configuré (256MB, 4000 fichiers)
- Docker layers caching
- Composer autoloader optimisé
- Nginx + PHP-FPM Unix socket
- Redis pour sessions/cache

## Sécurité

- Pas de dev dependencies en production
- Variables sensibles en secrets GitHub
- SSH keys dedicated (pas de password)
- Images scannées via Docker Hub
- Backup automatique pre-deploy

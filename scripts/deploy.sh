#!/bin/bash

# Script de déploiement HRManager
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-staging}
BACKUP_DIR="/backups"
DEPLOY_DIR="/opt/hrmanager"
COMPOSE_FILE="docker-compose.prod.yml"

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérifier que Docker est installé
if ! command -v docker &> /dev/null; then
    log_error "Docker n'est pas installé"
    exit 1
fi

# Vérifier que docker-compose est installé
if ! command -v docker-compose &> /dev/null; then
    log_error "Docker Compose n'est pas installé"
    exit 1
fi

# Se déplacer dans le répertoire de déploiement
cd "$DEPLOY_DIR" || {
    log_error "Impossible d'accéder à $DEPLOY_DIR"
    exit 1
}

log_info "Démarrage du déploiement en environnement: $ENVIRONMENT"

# Créer le répertoire de backup si inexistant
mkdir -p "$BACKUP_DIR"

# Fonction de rollback
rollback() {
    log_error "Échec du déploiement! Lancement du rollback..."
    
    # Restaurer la dernière backup si disponible
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/hrmanager_*.sql 2>/dev/null | head -1)
    
    if [ -f "$LATEST_BACKUP" ]; then
        log_warn "Restauration de la base de données depuis: $LATEST_BACKUP"
        docker-compose -f "$COMPOSE_FILE" exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" hrmanager < "$LATEST_BACKUP" || {
            log_error "Impossible de restaurer la base de données"
        }
    fi
    
    # Relancer les anciens containers
    log_warn "Relance des containers précédents"
    docker-compose -f "$COMPOSE_FILE" up -d || {
        log_error "Le rollback a échoué. Intervention manuelle nécessaire!"
        exit 1
    }
    
    log_info "Rollback terminé"
    exit 1
}

# Trap pour rollback en cas d'erreur
trap rollback ERR

# Backup de la base de données avant déploiement
log_info "Création d'une backup de la base de données..."
BACKUP_FILE="$BACKUP_DIR/hrmanager_$(date +%Y%m%d_%H%M%S)_pre_deploy.sql"

if docker-compose -f "$COMPOSE_FILE" ps | grep -q mysql; then
    docker-compose -f "$COMPOSE_FILE" exec -T mysql mysqldump \
        -u root -p"$DB_ROOT_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        hrmanager > "$BACKUP_FILE" || {
        log_warn "Impossible de créer une backup, poursuite du déploiement..."
    }
    
    if [ -f "$BACKUP_FILE" ]; then
        log_info "Backup créée: $BACKUP_FILE"
        # Compresser la backup
        gzip "$BACKUP_FILE"
        
        # Garder seulement les 10 dernières backups
        ls -t "$BACKUP_DIR"/hrmanager_*.sql.gz | tail -n +11 | xargs -r rm
    fi
else
    log_warn "MySQL n'est pas en cours d'exécution, pas de backup créée"
fi

# Pull des nouvelles images
log_info "Téléchargement des nouvelles images Docker..."
docker-compose -f "$COMPOSE_FILE" pull

# Arrêter les services non critiques
log_info "Arrêt des services de queue..."
docker-compose -f "$COMPOSE_FILE" stop queue-worker scheduler || true

# Démarrer les nouveaux containers
log_info "Démarrage des nouveaux containers..."
docker-compose -f "$COMPOSE_FILE" up -d --no-deps --build app

# Attendre que MySQL soit prêt
log_info "Attente de MySQL..."
for i in {1..30}; do
    if docker-compose -f "$COMPOSE_FILE" exec -T mysql mysqladmin ping -h localhost -u root -p"$DB_ROOT_PASSWORD" --silent; then
        log_info "MySQL est prêt"
        break
    fi
    sleep 2
done

# Exécuter les migrations
log_info "Exécution des migrations..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force || {
    log_error "Les migrations ont échoué! Rollback..."
    rollback
}

# Optimiser l'application
log_info "Optimisation de l'application..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache

# Relancer les workers
log_info "Redémarrage des workers..."
docker-compose -f "$COMPOSE_FILE" up -d queue-worker scheduler

# Redémarrer le scheduler
log_info "Redémarrage du planificateur..."
docker-compose -f "$COMPOSE_FILE" restart scheduler || true

# Nettoyage
log_info "Nettoyage des images et volumes inutilisés..."
docker system prune -f --filter "until=24h" || true

# Health check
log_info "Vérification de la santé de l'application..."
for i in {1..10}; do
    if curl -f -s http://localhost/api/health > /dev/null; then
        log_info "Application en bonne santé!"
        break
    fi
    log_warn "Attente de la santé de l'application... ($i/10)"
    sleep 5
    
    if [ $i -eq 10 ]; then
        log_error "Health check échoué! Rollback..."
        rollback
    fi
done

# Nettoyer les anciennes backups (garder 7 jours)
find "$BACKUP_DIR" -name "hrmanager_*.sql.gz" -mtime +7 -delete 2>/dev/null || true

log_info "Déploiement terminé avec succès!"

# Afficher les informations
log_info "Containers en cours:"
docker-compose -f "$COMPOSE_FILE" ps

echo ""
log_info "Version déployée:"
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan --version

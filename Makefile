.PHONY: up down shell migrate seed test build restart logs install composer cache-clear fresh help

# Default target
.DEFAULT_GOAL := help

# Colors
BLUE := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
NC := \033[0m # No Color

## help: Affiche cette aide
help:
	@echo "$(GREEN)HRManager - Commandes disponibles:$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  $(BLUE)%-15s$(NC) %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ""

## up: Démarre tous les services Docker
up:
	@echo "$(GREEN)🚀 Démarrage de HRManager...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)✅ Services démarrés !$(NC)"
	@echo "   🌐 API: http://localhost"
	@echo "   📧 MailHog: http://localhost:8025"

## down: Arrête tous les services Docker
down:
	@echo "$(YELLOW)🛑 Arrêt des services...$(NC)"
	docker-compose down
	@echo "$(GREEN)✅ Services arrêtés$(NC)"

## build: Reconstruit les images Docker
build:
	@echo "$(YELLOW)🔨 Reconstruction des images...$(NC)"
	docker-compose build --no-cache
	@echo "$(GREEN)✅ Images reconstruites$(NC)"

## restart: Redémarre tous les services
restart: down up

## shell: Ouvre un shell dans le conteneur app
shell:
	@echo "$(BLUE)🐚 Connexion au conteneur app...$(NC)"
	docker-compose exec app bash

## logs: Affiche les logs de tous les services
logs:
	@echo "$(BLUE)📋 Logs des services...$(NC)"
	docker-compose logs -f

## app-logs: Affiche les logs du conteneur app uniquement
app-logs:
	@echo "$(BLUE)📋 Logs de l'application...$(NC)"
	docker-compose logs -f app

## install: Installe les dépendances Composer
install:
	@echo "$(YELLOW)📦 Installation des dépendances...$(NC)"
	docker-compose exec app composer install --no-interaction --optimize-autoloader
	@echo "$(GREEN)✅ Dépendances installées$(NC)"

## update: Met à jour les dépendances Composer
update:
	@echo "$(YELLOW)📦 Mise à jour des dépendances...$(NC)"
	docker-compose exec app composer update
	@echo "$(GREEN)✅ Dépendances mises à jour$(NC)"

## migrate: Exécute les migrations
tmigrate:
	@echo "$(YELLOW)🗄️  Exécution des migrations...$(NC)"
	docker-compose exec app php artisan migrate --force
	@echo "$(GREEN)✅ Migrations exécutées$(NC)"

## migrate-fresh: Réinitialise la base de données et exécute les migrations
migrate-fresh:
	@echo "$(RED)⚠️  Réinitialisation de la base de données...$(NC)"
	docker-compose exec app php artisan migrate:fresh --force
	@echo "$(GREEN)✅ Base de données réinitialisée$(NC)"

## seed: Exécute les seeders
seed:
	@echo "$(YELLOW)🌱 Exécution des seeders...$(NC)"
	docker-compose exec app php artisan db:seed --force
	@echo "$(GREEN)✅ Seeders exécutés$(NC)"

## fresh: Réinitialise la BDD et exécute les seeders
fresh: migrate-fresh seed

## test: Exécute les tests
\test:
	@echo "$(BLUE)🧪 Exécution des tests...$(NC)"
	docker-compose exec app php artisan test

## cache-clear: Vide tous les caches
cache-clear:
	@echo "$(YELLOW)🧹 Vidage des caches...$(NC)"
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear
	@echo "$(GREEN)✅ Caches vidés$(NC)"

## cache: Met en cache la configuration, les routes et les vues
cache:
	@echo "$(YELLOW)⚡ Mise en cache...$(NC)"
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache
	@echo "$(GREEN)✅ Caches créés$(NC)"

## key: Génère une nouvelle clé d'application
key:
	@echo "$(YELLOW)🔑 Génération de la clé...$(NC)"
	docker-compose exec app php artisan key:generate --force
	@echo "$(GREEN)✅ Clé générée$(NC)"

## storage: Crée le lien symbolique storage
storage:
	@echo "$(YELLOW)🔗 Création du lien storage...$(NC)"
	docker-compose exec app php artisan storage:link --force
	@echo "$(GREEN)✅ Lien créé$(NC)"

## composer: Exécute une commande Composer (usage: make composer CMD="install")
composer:
	@echo "$(BLUE)📦 Exécution: composer $(CMD)...$(NC)"
	docker-compose exec app composer $(CMD)

## artisan: Exécute une commande Artisan (usage: make artisan CMD="route:list")
artisan:
	@echo "$(BLUE)🎨 Exécution: php artisan $(CMD)...$(NC)"
	docker-compose exec app php artisan $(CMD)

## mysql: Ouvre un shell MySQL
mysql:
	@echo "$(BLUE)🐬 Connexion à MySQL...$(NC)"
	docker-compose exec mysql mysql -uhrmanager -psecret hrmanager

## redis: Ovre un shell Redis
redis:
	@echo "$(BLUE)🔴 Connexion à Redis...$(NC)"
	docker-compose exec redis redis-cli

## status: Affiche le statut des conteneurs
status:
	@echo "$(BLUE)📊 Statut des conteneurs:$(NC)"
	docker-compose ps

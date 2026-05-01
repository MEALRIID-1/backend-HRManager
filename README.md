# HRManager - Backend API

Système de gestion des ressources humaines basé sur Laravel 11 avec API RESTful sécurisée via Sanctum.

## 🚀 Stack Technique

- **Laravel 11** + **PHP 8.3**
- **MySQL 8.0**
- **Redis** (Cache, Sessions, Queue)
- **Docker Compose** (App, MySQL, Nginx, Redis)
- **Laravel Sanctum** (Authentification API)

## 📁 Structure du Projet

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   ├── EmployeController.php
│   │   ├── CongeController.php
│   │   ├── ContratController.php
│   │   ├── NotificationController.php
│   │   ├── RapportController.php
│   │   ├── ParametreController.php
│   │   └── DashboardController.php
│   ├── Requests/           # FormRequest validation
│   ├── Resources/          # API Resources
│   └── Middleware/         # CheckRole, CheckPermission, LogActivity
├── Models/
│   ├── User.php
│   ├── Role.php
│   ├── Permission.php
│   ├── Conge.php
│   ├── Contrat.php
│   ├── Validation.php
│   ├── FichePaie.php
│   ├── Notification.php
│   └── ActivityLog.php
├── Services/               # Business Logic
│   ├── AuthService.php
│   ├── EmployeService.php
│   ├── CongeService.php
│   ├── ContratService.php
│   ├── NotificationService.php
│   ├── RapportService.php
│   └── PasswordService.php
└── Repositories/           # Repository Pattern
    ├── Contracts/
    └── Implementations
```

## 🐳 Démarrage avec Docker

### Prérequis
- Docker Desktop
- Docker Compose

### Installation

```bash
# Cloner le projet
cd backend-HRManager

# Lancer les containers
docker-compose up -d

# L'application initialise automatiquement :
# - Composer install
# - Génération de la clé APP_KEY
# - Migrations
# - Seeders (création de l'admin)
# - Storage link
```

### Accès

| Service | URL | Identifiants |
|---------|-----|--------------|
| API | http://localhost/api/v1 | - |
| MySQL | localhost:3306 | hrmanager/secret |
| Redis | localhost:6379 | - |

### Utilisateur Admin par défaut
- **Email** : `admin@hrmanager.local`
- **Mot de passe** : `Admin@2024!`

## 🔐 API Endpoints

### Authentification (Public)
```
POST /api/v1/login
POST /api/v1/forgot-password
POST /api/v1/reset-password
```

### Authentification (Protégé)
```
POST   /api/v1/logout
POST   /api/v1/logout-all
GET    /api/v1/me
POST   /api/v1/change-password
```

### Employés
```
GET    /api/v1/employes
POST   /api/v1/employes
GET    /api/v1/employes/{id}
PUT    /api/v1/employes/{id}
DELETE /api/v1/employes/{id}
POST   /api/v1/employes/{id}/activate
POST   /api/v1/employes/{id}/deactivate
GET    /api/v1/employes/managers/list
GET    /api/v1/employes/departements/list
GET    /api/v1/employes/statistiques/global
```

### Congés
```
GET    /api/v1/conges
POST   /api/v1/conges
GET    /api/v1/conges/{id}
PUT    /api/v1/conges/{id}
DELETE /api/v1/conges/{id}
POST   /api/v1/conges/{id}/approve
POST   /api/v1/conges/{id}/reject
GET    /api/v1/conges/en-attente/list
GET    /api/v1/conges/mes-conges/list
GET    /api/v1/conges/solde/mine
GET    /api/v1/conges/statistiques/global
```

### Contrats
```
GET    /api/v1/contrats
POST   /api/v1/contrats
GET    /api/v1/contrats/{id}
PUT    /api/v1/contrats/{id}
DELETE /api/v1/contrats/{id}
POST   /api/v1/contrats/{id}/approve
POST   /api/v1/contrats/{id}/terminate
POST   /api/v1/contrats/{id}/renew
GET    /api/v1/contrats/expirants/{jours}
GET    /api/v1/contrats/mes-contrats/list
GET    /api/v1/contrats/actuel/mine
GET    /api/v1/contrats/statistiques/global
```

### Dashboard & Rapports
```
GET /api/v1/dashboard
GET /api/v1/dashboard/admin
GET /api/v1/dashboard/manager
GET /api/v1/dashboard/employee
GET /api/v1/dashboard/rh

GET /api/v1/rapports/dashboard
GET /api/v1/rapports/effectif
GET /api/v1/rapports/conges
GET /api/v1/rapports/contrats
GET /api/v1/rapports/masse-salariale
GET /api/v1/rapports/activites
```

### Notifications
```
GET    /api/v1/notifications
GET    /api/v1/notifications/{id}
POST   /api/v1/notifications/{id}/read
POST   /api/v1/notifications/read-all
DELETE /api/v1/notifications/{id}
GET    /api/v1/notifications/non-lues/list
GET    /api/v1/notifications/count/non-lues
```

## 👥 Rôles et Permissions

| Rôle | Permissions |
|------|-------------|
| **Admin** | Toutes les permissions |
| **RH** | Employés, Contrats, Fiches de paie, Rapports |
| **Manager** | Validation congés, Vue équipe, Rapports |
| **Employé** | Congés (CRUD), Vue personnelle |

## 📊 Modèles Principaux

### User (Employé)
- Informations personnelles
- Matricule, Poste, Département
- Manager hiérarchique
- Historique connexion (tentatives, verrouillage)

### Conge
- Types : Congé payé, RTT, Sans solde, Maladie, Formation
- Workflow : En attente → Approuvé/Refusé
- Calcul automatique jours ouvrés
- Gestion remplaçant

### Contrat
- Types : CDI, CDD, Stage, Alternance, Intérim, Freelance
- Suivi dates de fin
- Documents attachés
- Workflow validation

### Validation
- Multi-niveaux de validation
- Commentaires des validateurs
- Historique complet

## 🧪 Tests

```bash
# Tests unitaires
php artisan test

# Avec coverage
php artisan test --coverage
```

## 📝 Standards de Code

- **PSR-12** : Style de code PHP
- **SOLID** : Principes de conception
- **DRY** : Don't Repeat Yourself
- Transactions DB pour toutes les opérations critiques
- Try/catch sur tous les services

## 🐞 Debugging

```bash
# Logs Laravel
docker-compose logs -f app

# Accès container
 docker-compose exec app bash

# Commandes Artisan
 docker-compose exec app php artisan [commande]
```

## 📦 Dépendances Principales

```json
{
  "laravel/framework": "^11.0",
  "laravel/sanctum": "^4.0",
  "predis/predis": "^2.0"
}
```

## 🔒 Sécurité

- Sanctum tokens avec expiration
- Rate limiting sur auth
- Hashage des mots de passe (bcrypt)
- Protection contre brute-force (verrouillage compte)
- Validation stricte des données (FormRequest)
- SQL injection protection via Eloquent

## 📄 License

MIT License

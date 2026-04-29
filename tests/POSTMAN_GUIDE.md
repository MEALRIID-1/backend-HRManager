# HRManager CRUD Tests - Postman Collection Guide

## 📋 Vue d'ensemble
Cette collection Postman teste tous les endpoints CRUD (Create, Read, Update, Delete) de l'API HRManager sur les ressources principales :
- **Employees** (Employés)
- **Contracts** (Contrats)
- **Permissions** (Permissions)
- **Roles** (Rôles)
- **Notifications** (Notifications)

## 🚀 Installation

### 1. Importer la collection dans Postman
1. Ouvrir **Postman** (ou télécharger depuis https://www.postman.com/downloads/)
2. Cliquer sur **Import** (en haut à gauche)
3. Sélectionner le fichier `Postman_CRUD_Collection.json`
4. La collection **HRManager CRUD Tests** apparaîtra dans votre sidebar

### 2. Configurer l'environnement
La collection utilise des variables d'environnement :
- `base_url` : `http://localhost:8000/api` (à ajuster selon votre déploiement)
- `admin_token` : Token JWT (rempli automatiquement lors du login)
- `rh_token` : Token JWT RH
- `employee_id` : ID de l'employé créé (rempli automatiquement)
- `contract_id` : ID du contrat créé (rempli automatiquement)

Pour personnaliser :
1. Cliquer sur l'icône **Environment** (engrenage) en haut à droite
2. Créer un nouvel environnement ou modifier le défaut
3. Remplir les variables

## 📝 Exécution des tests

### Option 1 : Tests manuels (recommandé pour démarrer)
1. **Se connecter d'abord**
   - Exécuter `Authentication → Login - Admin`
   - Le token est automatiquement sauvegardé dans `admin_token`

2. **Tester les CRUD Employees** (dans l'ordre)
   ```
   1. CREATE - Add Employee
   2. READ - List All Employees
   3. READ - Get Employee By ID
   4. UPDATE - Edit Employee
   5. DELETE - Remove Employee
   ```

3. **Tester les autres ressources**
   - Contracts, Permissions, Roles, Notifications (même ordre)

### Option 2 : Exécution automatisée (Runner)
1. Cliquer sur **Run** (play icon à gauche)
2. Sélectionner la collection
3. Cliquer sur **Run HRManager CRUD Tests**
4. Les tests s'exécuteront séquentiellement avec les assertions

## ✅ Résultats attendus

| Endpoint | Méthode | Code attendu | Notes |
|----------|---------|--------------|-------|
| `/login` | POST | 200 | Récupère le JWT token |
| `/health` | GET | 200/503 | État de santé de l'API |
| `/employees` | GET | 200 | Liste (nécessite `view-employees`) |
| `/employees` | POST | 201 | Création (nécessite `create-employees`) |
| `/employees/{id}` | GET | 200/404 | Lecture par ID |
| `/employees/{id}` | PUT | 200/404 | Mise à jour |
| `/employees/{id}` | DELETE | 200/404 | Suppression (soft delete) |
| `/employees/stats` | GET | 200/403 | Statistiques |
| `/contracts` | GET/POST | 200/201 | Idem pour contracts |
| `/permissions` | GET/POST | 200/201 | Idem pour permissions |
| `/roles` | GET/POST | 200/201 | Idem pour roles |
| `/notifications` | GET | 200 | Idem pour notifications |

## 🔐 Authentification requise
Tous les endpoints protégés par `auth:sanctum` nécessitent :
```
Authorization: Bearer <token_jwt>
```

Les tokens des seeders :
- Admin : `admin@hrmanager.com` / `password123`
- RH : `rh@hrmanager.com` / `password123`
- Manager : `manager@hrmanager.com` / `password123`
- Employé : `employe@hrmanager.com` / `password123`

## 🧪 Permissions requises par endpoint

| Endpoint | Permission requise |
|----------|-------------------|
| `GET /employees` | `view-employees` |
| `POST /employees` | `create-employees` |
| `PUT /employees/{id}` | `edit-employees` |
| `DELETE /employees/{id}` | `delete-employees` |
| `GET /contracts` | `view-contracts` |
| `POST /contracts` | `create-contracts` |
| `PUT /contracts/{id}` | `update` (policy) |
| `DELETE /contracts/{id}` | `delete-contracts` |
| `GET /roles` | `view-roles` |
| `POST /roles` | `create-roles` |
| `GET /permissions` | `view-permissions` |
| `POST /permissions` | `manage-rbac` |

## 🐛 Dépannage

### Erreur 401 Unauthorized
- Vérifier que le login s'est exécuté correctement
- Vérifier que le token est stocké dans `admin_token`
- Vérifier l'expiration du token (config: `SANCTUM_TOKEN_EXPIRATION`)

### Erreur 403 Forbidden
- L'utilisateur n'a pas les permissions requises
- Utiliser un compte avec plus de permissions (admin au lieu de employé)

### Erreur 500 Internal Server Error
- Vérifier les logs du conteneur : `docker compose logs app --tail 50`
- Vérifier que Sanctum est correctement installé

### Erreur 404 Not Found
- L'ID utilisé n'existe pas dans la base de données
- Vérifier que `employee_id` et `contract_id` ont bien été remplis par les tests précédents

## 📊 Variables d'environnement dynamiques
La collection utilise des **tests scripts** pour extraire et stocker automatiquement les IDs :

```javascript
// Exemple : sauvegarde de l'ID d'un employé créé
pm.environment.set('employee_id', jsonData.data.id);

// Utilisation dans les requêtes suivantes
GET /employees/{{employee_id}}
```

## 🔄 Flux de test recommandé

```
1. Authentication → Login - Admin
   ├─ Sauvegarde admin_token
   
2. Employees - CRUD
   ├─ CREATE → reçoit employee_id
   ├─ READ (list)
   ├─ READ (by ID) → utilise employee_id
   ├─ UPDATE → utilise employee_id
   └─ DELETE → utilise employee_id
   
3. Contracts - CRUD
   ├─ CREATE → reçoit contract_id
   ├─ READ (list)
   ├─ READ (by ID) → utilise contract_id
   ├─ UPDATE → utilise contract_id
   └─ DELETE → utilise contract_id
   
4. Permissions - CRUD (list uniquement par défaut)
5. Roles - CRUD (list uniquement par défaut)
6. Notifications - CRUD (list uniquement)
```

## 📈 Assertions des tests
Chaque requête contient des assertions qui valident :
- ✅ Code de réponse HTTP attendu
- ✅ Champ `success` à true
- ✅ Présence des données retournées
- ✅ Gestion des erreurs (404, 403, etc.)

## 🎯 Checklist CRUD complète

- [ ] Login réussit et retourne un token
- [ ] CREATE employees : status 201, reçoit ID
- [ ] READ employees list : status 200, retourne tableau
- [ ] READ employee by ID : status 200 ou 404
- [ ] UPDATE employee : status 200, données mises à jour
- [ ] DELETE employee : status 200, soft delete
- [ ] CREATE contracts : status 201
- [ ] READ contracts : status 200
- [ ] UPDATE contracts : status 200
- [ ] DELETE contracts : status 200
- [ ] CREATE permissions : status 201 ou 403
- [ ] READ permissions : status 200
- [ ] CREATE roles : status 201 ou 403
- [ ] READ roles : status 200
- [ ] READ notifications : status 200 ou 401

## 📚 Ressources supplémentaires
- API Docs : `README-EMPLOYEE-API.md`
- Laravel Sanctum : https://laravel.com/docs/sanctum
- Postman Docs : https://learning.postman.com/

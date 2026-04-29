# 🧪 Tests CRUD API HRManager

Ce dossier contient les outils pour tester les endpoints CRUD de l'API HRManager.

## 📋 Fichiers disponibles

### 1. **Postman_CRUD_Collection.json**
Collection Postman complète avec tous les tests CRUD.
- ✅ Tests automatisés avec assertions
- ✅ Variables d'environnement dynamiques
- ✅ Extraction automatique des IDs
- ✅ Gestion des tokens JWT

**Utilisation :**
```
1. Importer dans Postman
2. Configurer base_url (par défaut: http://localhost:8000/api)
3. Exécuter la collection (Run)
```

**Lien :** [POSTMAN_GUIDE.md](POSTMAN_GUIDE.md)

---

### 2. **Test-API-CRUD.ps1**
Script PowerShell pour tester l'API sans Postman.
- ✅ Tests directs via HTTP
- ✅ Logs colorisés
- ✅ Résumé avec taux de réussite
- ✅ Gestion des erreurs

**Utilisation :**
```powershell
# Mode normal
.\Test-API-CRUD.ps1

# Mode verbose (affiche les réponses complètes)
.\Test-API-CRUD.ps1 -Verbose

# URL personnalisée
.\Test-API-CRUD.ps1 -BaseUrl "http://api.example.com/api"
```

**Exécution :**
```powershell
# Permettre l'exécution des scripts
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Lancer le test
cd c:\Users\USER\Documents\fullstack\HRManager\HRManager-projet\backend-HRManager\tests
.\Test-API-CRUD.ps1
```

---

### 3. **POSTMAN_GUIDE.md**
Guide détaillé pour utiliser la collection Postman.

---

## 🎯 Routes testées

### ✓ Authentication
- `POST /login` - Authentification avec JWT

### ✓ Employees (CRUD)
- `GET /employees` - Lister tous les employés
- `POST /employees` - Créer un employé
- `GET /employees/{id}` - Récupérer un employé
- `PUT /employees/{id}` - Modifier un employé
- `DELETE /employees/{id}` - Supprimer un employé
- `GET /employees/stats` - Statistiques

### ✓ Contracts (CRUD)
- `GET /contracts` - Lister tous les contrats
- `POST /contracts` - Créer un contrat
- `GET /contracts/{id}` - Récupérer un contrat
- `PUT /contracts/{id}` - Modifier un contrat
- `DELETE /contracts/{id}` - Supprimer un contrat
- `GET /contracts/expiring` - Contrats expirant

### ✓ Permissions (READ + CREATE)
- `GET /permissions` - Lister les permissions
- `POST /permissions` - Créer une permission
- `GET /permissions/by-module` - Permissions par module

### ✓ Roles (READ + CREATE)
- `GET /roles` - Lister les rôles
- `POST /roles` - Créer un rôle

### ✓ Notifications (READ)
- `GET /notifications` - Lister les notifications
- `GET /notifications/unread-count` - Nombre non lus

---

## 🔐 Identifiants de test

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@hrmanager.com | password123 | Admin |
| rh@hrmanager.com | password123 | RH |
| manager@hrmanager.com | password123 | Manager |
| employe@hrmanager.com | password123 | Employé |

---

## 🚀 Démarrage rapide

### Option 1 : Avec Postman
1. Ouvrir Postman
2. Cliquer sur **Import** → **File** → Sélectionner `Postman_CRUD_Collection.json`
3. Cliquer sur **Run** (play icon)
4. Observer les résultats

### Option 2 : Avec PowerShell
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
.\Test-API-CRUD.ps1 -Verbose
```

### Option 3 : cURL (manuel)
```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@hrmanager.com","password":"password123"}'

# Lister les employés
curl -X GET http://localhost:8000/api/employees \
  -H "Authorization: Bearer TOKEN_JWT"
```

---

## ✅ Vérification des routes CRUD

Après exécution des tests, vérifier :

### Employees
- [ ] CREATE retourne 201 ou 200
- [ ] READ list retourne 200
- [ ] READ by ID retourne 200 ou 404
- [ ] UPDATE retourne 200 ou 404
- [ ] DELETE retourne 200 ou 404

### Contracts
- [ ] CREATE retourne 201 ou 200
- [ ] READ list retourne 200
- [ ] READ by ID retourne 200 ou 404
- [ ] UPDATE retourne 200 ou 404
- [ ] DELETE retourne 200 ou 404

### Permissions & Roles
- [ ] LIST retourne 200
- [ ] CREATE retourne 201 ou 403

### Notifications
- [ ] LIST retourne 200
- [ ] UNREAD COUNT retourne 200

---

## 🐛 Dépannage

### Erreur : `Connection refused` ou `Impossible de se connecter`
➜ Vérifier que l'API est en cours d'exécution :
```bash
docker compose ps
docker compose logs app --tail 20
```

### Erreur : `401 Unauthorized`
➜ Vérifier les identifiants et que le JWT est valide :
```
POST /login avec email/password correct
Copier le token dans Authorization: Bearer <token>
```

### Erreur : `403 Forbidden`
➜ L'utilisateur n'a pas les permissions :
```
Utiliser un compte Admin au lieu de Employé
Vérifier les rôles et permissions assignés
```

### Erreur : `500 Internal Server Error`
➜ Problème serveur :
```bash
docker compose logs app --tail 100 | grep ERROR
```

---

## 📊 Exemple de résultat

```
╔════════════════════════════════════════════════════════════════╗
║       HRManager CRUD API Tests - PowerShell Script            ║
║       Base URL: http://localhost:8000/api
╚════════════════════════════════════════════════════════════════╝

► Testing Health Endpoint
✓ [GET] http://localhost:8000/api/health - Status 200

► Testing Authentication
✓ [POST] http://localhost:8000/api/login - Status 200
  Token Admin: eyJ0eXAiOiJKV1QiLC...

► Testing Employees CRUD
✓ [POST] http://localhost:8000/api/employees - Status 201
  Created Employee ID: 42
✓ [GET] http://localhost:8000/api/employees - Status 200
✓ [GET] http://localhost:8000/api/employees/42 - Status 200
✓ [PUT] http://localhost:8000/api/employees/42 - Status 200
✓ [DELETE] http://localhost:8000/api/employees/42 - Status 200

...

╔════════════════════════════════════════════════════════════════╗
║                        TEST SUMMARY                            ║
╠════════════════════════════════════════════════════════════════╣
Total Tests: 18
Passed: 18
Failed: 0
Pass Rate: 100%
╚════════════════════════════════════════════════════════════════╝
```

---

## 🔗 Ressources

- 📚 [API Documentation](../README-EMPLOYEE-API.md)
- 🐳 [Docker Compose Setup](../docker-compose.yml)
- 🔐 [Sanctum Documentation](https://laravel.com/docs/sanctum)
- 📮 [Postman Documentation](https://learning.postman.com/)

---

**Dernière mise à jour :** 28/04/2026
**Auteur :** HRManager Dev Team

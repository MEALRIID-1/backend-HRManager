# API Employé - Documentation

Documentation des endpoints API pour l'interface employé du frontend HRManager.

## Configuration CORS

Pour permettre les requêtes depuis le frontend Next.js, la configuration CORS a été mise à jour dans `config/cors.php` :

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'],
'supports_credentials' => true, // Nécessaire pour Sanctum
```

**Variables d'environnement requises** (dans `.env`) :
```env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000,localhost:8000
FRONTEND_URL=http://localhost:3000
```

## Données de Test

Un seeder spécifique `TestEmployeeSeeder` crée des données de test pour l'interface employé.

### Identifiants de connexion

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Employé | `employe.demo@hrmanager.com` | `password123` |
| Manager | `manager.test@hrmanager.com` | `password123` |

### Exécution du seeder

```bash
# Lancer tous les seeders (incluant les données de test)
php artisan db:seed

# Lancer uniquement le seeder employé
php artisan db:seed --class=TestEmployeeSeeder
```

### Données créées

- **Employé** : Employe Demo (Informatique)
- **Manager** : Manager Test (Responsable Informatique)
- **Contrat** : CDI actif, 3500€/mois
- **Congés** : 4 congés (approuvé, soumis, validé manager, refusé)
- **Corbeille** : 1 congé annulé pour tester la restauration
- **Notifications** : 4 notifications (3 non lues, 1 lue)

## Endpoints API

### Profil Employé

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/me` | Profil complet de l'utilisateur |
| `POST` | `/api/me/photo` | Upload photo de profil (multipart/form-data) |
| `PUT` | `/api/me/password` | Changer le mot de passe |

**Exemple réponse GET /api/me** :
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nom": "Demo",
    "prenom": "Employe",
    "email": "employe.demo@hrmanager.com",
    "telephone": "0612345678",
    "adresse": "123 Rue de la Paix, 75000 Paris",
    "dateNaissance": "1990-05-15",
    "dateEmbauche": "2022-01-10",
    "avatar": null,
    "departementId": 1,
    "manager": {
      "id": 2,
      "nom": "Test",
      "prenom": "Manager"
    }
  }
}
```

### Contrats

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/me/contrats` | Liste paginée des contrats |
| `GET` | `/api/me/contrats/actif` | Contrat actif uniquement |

**Exemple réponse GET /api/me/contrats/actif** :
```json
{
  "success": true,
  "data": {
    "id": 1,
    "type": "cdi",
    "etat": "en_cours",
    "date_debut": "2022-01-10",
    "date_fin": null,
    "salaire_base": 3500.00
  }
}
```

### Congés

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/me/conges` | Liste des congés (filtres disponibles) |
| `GET` | `/api/me/conges/solde` | Soldes de congés par type |
| `GET` | `/api/me/conges/corbeille/count` | Nombre de congés annulés |
| `POST` | `/api/me/conges` | Créer une nouvelle demande |
| `DELETE` | `/api/me/conges/{id}` | Annuler (soft delete) |
| `PUT` | `/api/me/conges/{id}/restaurer` | Restaurer un congé annulé |

**Filtres GET /api/me/conges** :
- `?exclure_annulees=1` : Exclure les congés annulés
- `?annulees_seulement=1` : Afficher uniquement les annulés (corbeille)
- `?limit=5` : Limiter le nombre de résultats

**Exemple réponse GET /api/me/conges** :
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "conge_annuel",
      "statut": "approuve",
      "dateDebut": "2025-02-15",
      "dateFin": "2025-02-19",
      "nombreJours": 5,
      "motif": "Vacances d'hiver",
      "validations": [
        {
          "niveau": 1,
          "decision": "approuve",
          "commentaire": "OK pour moi",
          "dateValidation": "2025-01-10T14:30:00Z",
          "validateur": {
            "id": 2,
            "nom": "Test",
            "prenom": "Manager"
          }
        }
      ],
      "deletedAt": null
    }
  ]
}
```

**Exemple requête POST /api/me/conges** :
```json
{
  "type": "conge_annuel",
  "date_debut": "2025-06-01",
  "date_fin": "2025-06-10"
}
```

### Notifications

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/notifications` | Liste des notifications |
| `GET` | `/api/notifications/count-non-lues` | Nombre de notifications non lues |
| `PATCH` | `/api/notifications/{id}/lire` | Marquer comme lue |
| `PATCH` | `/api/notifications/lire-tout` | Tout marquer comme lues |

**Filtres GET /api/notifications** :
- `?statut=non_lu` : Notifications non lues uniquement
- `?page=1&limit=10` : Pagination

**Exemple réponse GET /api/notifications/count-non-lues** :
```json
{
  "success": true,
  "data": {
    "count": 3
  }
}
```

## Codes HTTP

| Code | Signification |
|------|---------------|
| `200` | Succès |
| `201` | Créé avec succès |
| `401` | Non authentifié |
| `403` | Non autorisé |
| `404` | Ressource non trouvée |
| `422` | Validation échouée |
| `500` | Erreur serveur |

## Types de Congés

Les types de congés supportés :
- `conge_annuel` - Congés payés
- `maladie` - Arrêt maladie
- `maternite` - Congé maternité
- `paternite` - Congé paternité
- `sans_solde` - Congé sans solde
- `exceptionnel` - Congé exceptionnel

## Types de Notifications

Les types de notifications supportés :
- `CONGE_SOUMIS` - Nouvelle demande soumise
- `CONGE_APPROUVE` - Demande approuvée
- `CONGE_REFUSE` - Demande refusée
- `CONTRAT_EXPIRE_BIENTOT` - Contrat expirant
- `ANNIVERSAIRE_EMBAUCHE` - Anniversaire d'embauche
- `DOCUMENT_REQUIS` - Document manquant
- `RAPPEL_EVALUATION` - Évaluation annuelle
- `SYSTEME` - Notification système

## Workflow des Congés

```
BROUILLON → SOUMIS → VALIDE_MANAGER → VALIDE_RH → APPROUVE
                ↓           ↓              ↓
            REFUSE      REFUSE         REFUSE
```

Un congé peut être annulé (soft delete) uniquement s'il est dans l'un des états :
- `brouillon`
- `soumis`
- `valide_manager`
- `valide_rh`

## Test avec cURL

### Authentification
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"employe.demo@hrmanager.com","password":"password123"}'
```

### Récupérer le profil
```bash
curl http://localhost:8000/api/me \
  -H "Authorization: Bearer TOKEN"
```

### Créer un congé
```bash
curl -X POST http://localhost:8000/api/me/conges \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"conge_annuel","date_debut":"2025-07-01","date_fin":"2025-07-10"}'
```

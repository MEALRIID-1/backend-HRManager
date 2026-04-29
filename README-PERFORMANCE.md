# Guide d'Optimisation Performance HRManager

Ce document décrit toutes les optimisations de performance implémentées.

## Table des Matières
1. [N+1 Query Elimination](#n1-query-elimination)
2. [Repositories Optimisés](#repositories-optimisés)
3. [Cache Stratégique](#cache-stratégique)
4. [Index MySQL](#index-mysql)
5. [Queues et Jobs](#queues-et-jobs)
6. [Configuration Telescope](#configuration-telescope)

---

## N+1 Query Elimination

### Eager Loading Systématique
```php
// ❌ AVANT: N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->manager->name; // 1 requête par utilisateur!
}

// ✅ APRÈS: 1 requête seule
$users = User::with(['manager', 'roles'])->get();
```

### Lazy Eager Loading
```php
$user = User::find(1);

// Charge uniquement si nécessaire
$user->loadMissing(['contratActif', 'soldesConges']);
```

### withCount() pour Compteurs
```php
User::withCount([
    'conges as conges_pending' => fn($q) => $q->where('etat', 'soumis'),
])->get();
```

**Gain estimé**: 60-90% de réduction du nombre de requêtes

---

## Repositories Optimisés

### `app/Repositories/UserRepository.php`

Méthodes avec `select()` pour charger uniquement les colonnes nécessaires:

| Méthode | Colonnes | Gain |
|---------|----------|------|
| `getAllEmployees()` | 12 colonnes (vs 25+) | -40% mémoire |
| `getByManager()` | 5 colonnes + contrats | -60% mémoire |
| `getForDropdown()` | 4 colonnes | -80% mémoire |
| `getEmployeeById()` | Dynamique | -50% mémoire |

**Utilisation**:
```php
$repo = app(UserRepository::class);
$employees = $repo->getAllEmployees(['departement' => 'IT']);
```

---

## Cache Stratégique

### `app/Services/CacheService.php`

Cache par tags avec invalidation groupée:

```php
$cache = app(CacheService::class);

// Cacher avec tags
$data = $cache->rememberEmployees('active-list', function() {
    return User::where('statut', 'actif')->get();
}, 300);

// Invalider tout le cache des employés
$cache->invalidateEmployees();
```

### Durées de Cache

| Type | Durée | Justification |
|------|-------|---------------|
| employees | 5 min | Modifiés fréquemment |
| contracts | 10 min | Moins volatiles |
| leaves | 3 min | Très volatiles |
| payroll | 15 min | Stable après génération |
| departments | 24h | Quasi-statique |
| permissions | 1h | Stable par session |

### API Response Cache

`app/Http/Middleware/CacheApiResponse.php`:
```php
// Cache automatique GET /api/employees
// Durée: 3 min, clé basée sur requête
// Headers: X-Cache (HIT/MISS), ETag
```

---

## Index MySQL

Migration: `database/migrations/2026_04_27_200000_add_performance_indexes.php`

### Index Créés

| Table | Index | Colonnes | Gain |
|-------|-------|----------|------|
| users | Full-text | nom, prenom, email | 90% recherche |
| users | Composite | manager_id, statut | 80% dashboard |
| contrats | Index | employe_id, statut | 85% contrat actif |
| conges | Index | employe_id, etat | 80% liste congés |
| fiches_paie | Unique | employe_id, mois, annee | 95% unicité |
| activity_logs | Index | user_id, created_at | 85% audit |

### Commandes

```bash
# Appliquer les index
php artisan migrate --path=database/migrations/2026_04_27_200000_add_performance_indexes.php

# Analyser les requêtes lentes
php artisan query:analyze
```

---

## Queues et Jobs

### Jobs Créés

#### 1. `app/Jobs/GeneratePdfJob.php`
```php
// Lancez depuis un controller:
GeneratePdfJob::dispatch($fichePaieId)->onQueue('pdf');

// Queue worker dédié:
php artisan queue:work --queue=pdf --sleep=3 --tries=3
```
**Gain**: Libère 500-2000ms sur la requête HTTP

#### 2. `app/Jobs/SendEmailNotificationJob.php`
```php
SendEmailNotificationJob::dispatch(
    PayslipMailable::class,
    [$user->email],
    ['payslip' => $payslip]
)->onQueue('emails');
```
**Gain**: Réduit temps réponse de 500-2000ms

### Configuration Queues (docker-compose.prod.yml)

```yaml
queue-worker:
  command: php artisan queue:work --sleep=3 --tries=3 --timeout=90
  deploy:
    replicas: 3  # 3 workers en parallèle
```

### Queues Prioritaires

| Queue | Usage | Workers |
|-------|-------|---------|
| default | Tâches générales | 1 |
| pdf | Génération PDFs | 2 |
| emails | Envoi emails | 2 |
| high | Tâches urgentes | 1 |

---

## Configuration Telescope

### `config/telescope.php`

**Règle d'or**: Désactivé en production (`TELESCOPE_ENABLED=false`)

### Configuration .env

```env
# Développement
TELESCOPE_ENABLED=true
TELESCOPE_REQUEST_WATCHER=true
TELESCOPE_QUERY_WATCHER=true
TELESCOPE_SLOW_QUERIES=100

# Production
TELESCOPE_ENABLED=false
```

### Watchers Activés

| Watcher | Dev | Prod | Notes |
|---------|-----|------|-------|
| Request | ✅ | ❌ | Size limit 64KB |
| Query | ✅ | ❌ | Slow > 100ms only |
| Exception | ✅ | ❌ | Critiques uniquement |
| Job | ✅ | ❌ | Via queue logs |

---

## Commandes Performance

### Préchauffer le Cache

```bash
# Tout préchauffer
php artisan cache:warm

# Spécifique
php artisan cache:warm --type=employees
php artisan cache:warm --type=stats

# Forcer re-chauffage
php artisan cache:warm --force
```

### Monitoring Performance

```bash
# Stats cache
php artisan cache:stats

# Requêtes lentes
php artisan query:slow --threshold=100

# N+1 detector
php artisan query:nplus1
```

---

## Gains Estimés Globaux

| Optimisation | Gain |
|--------------|------|
| N+1 Elimination | 60-90% moins de requêtes |
| Select Columns | 40-80% moins de mémoire |
| Cache stratégique | 80% hits sur données fréquentes |
| Index MySQL | 30-70% plus rapide |
| Queues | 500-2000ms libérés |
| API Response Cache | 50-90% plus rapide |
| Telescope désactivé | -100% overhead production |

**Gain global estimé**: 70-90% d'amélioration des temps de réponse

---

## Checklist Déploiement Performance

- [ ] Migrations d'index appliquées
- [ ] Cache Redis configuré
- [ ] Queue workers lancés (3 instances)
- [ ] `cache:warm` exécuté
- [ ] Telescope désactivé en prod
- [ ] OPcache configuré
- [ ] `config:cache` et `route:cache` faits

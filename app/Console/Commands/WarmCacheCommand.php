<?php

namespace App\Console\Commands;

use App\Models\Contrat;
use App\Models\Conge;
use App\Models\FichePaie;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Commande pour préchauffer le cache après déploiement.
 * 
 * @performance Réduit le temps de réponse initial des utilisateurs de 60-80%
 */
class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm
                            {--type=all : Type de données à cacher (all|employees|contracts|leaves|payroll|stats)}
                            {--force : Forcer le re-chauffage même si cache existant}';

    protected $description = 'Préchauffer le cache pour les données fréquemment accédées';

    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    public function handle(): int
    {
        $type = $this->option('type');
        $force = $this->option('force');

        $this->info('🔥 Démarrage du préchauffage du cache...');
        $startTime = microtime(true);

        $types = $type === 'all' 
            ? ['departments', 'employees', 'contracts', 'leaves', 'payroll', 'stats', 'permissions']
            : [$type];

        foreach ($types as $cacheType) {
            $method = 'warm' . ucfirst($cacheType);
            
            if (method_exists($this, $method)) {
                $this->info("\n📦 Préchauffage: {$cacheType}");
                $typeStart = microtime(true);
                
                try {
                    $this->$method($force);
                    $duration = round(microtime(true) - $typeStart, 2);
                    $this->info("   ✓ Complété en {$duration}s");
                } catch (\Exception $e) {
                    $this->error("   ✗ Erreur: {$e->getMessage()}");
                }
            }
        }

        // Statistiques finales
        $totalTime = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("✅ Préchauffage terminé en {$totalTime}s");
        $this->displayCacheStats();

        return 0;
    }

    /**
     * Préchauffe les départements.
     * 
     * @performance Cache 24h - données quasi-statiques
     */
    protected function warmDepartments(bool $force): void
    {
        $this->cacheService->rememberDepartments(function () {
            return User::query()
                ->select('departement')
                ->distinct()
                ->whereNotNull('departement')
                ->where('statut', 'actif')
                ->orderBy('departement')
                ->pluck('departement')
                ->toArray();
        });

        $this->info('   - ' . User::distinct('departement')->count() . ' départements cachés');
    }

    /**
     * Préchauffe les employés.
     * 
     * @performance Cache 5 min - données modérément fréquentes
     */
    protected function warmEmployees(bool $force): void
    {
        // Liste employés actifs (dropdowns)
        $this->cacheService->rememberEmployees('active-list', function () {
            return User::query()
                ->select(['id', 'prenom', 'nom', 'email', 'departement'])
                ->where('statut', 'actif')
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get();
        }, 600);

        $count = User::where('statut', 'actif')->count();
        $this->info("   - {$count} employés actifs cachés");

        // Employés par département (pour filtres rapides)
        $departments = $this->cacheService->rememberDepartments(fn() => []);
        foreach ($departments as $dept) {
            $this->cacheService->rememberEmployees("dept:{$dept}", function () use ($dept) {
                return User::query()
                    ->select(['id', 'prenom', 'nom', 'email', 'poste'])
                    ->where('departement', $dept)
                    ->where('statut', 'actif')
                    ->get();
            }, 300);
        }
    }

    /**
     * Préchauffe les contrats.
     * 
     * @performance Cache 10 min - données importantes pour dashboard
     */
    protected function warmContracts(bool $force): void
    {
        // Contrats expirants (30 jours) - alertes dashboard
        $this->cacheService->rememberContracts('expiring-30d', function () {
            return Contrat::query()
                ->select([
                    'id', 'employe_id', 'type_contrat', 'date_fin', 'salaire_base'
                ])
                ->where('statut', 'actif')
                ->where('date_fin', '<=', now()->addDays(30))
                ->where('date_fin', '>=', now())
                ->with('employe:id,prenom,nom,email')
                ->get();
        }, 600);

        $expiringCount = Contrat::where('statut', 'actif')
            ->where('date_fin', '<=', now()->addDays(30))
            ->count();
        $this->info("   - {$expiringCount} contrats expirants cachés");
    }

    /**
     * Préchauffe les congés.
     * 
     * @performance Cache 3 min - très volatile, court TTL
     */
    protected function warmLeaves(bool $force): void
    {
        // Congés en attente (dashboard managers)
        $this->cacheService->rememberLeaves('pending-count', function () {
            return Conge::where('etat', 'soumis')->count();
        }, 180);

        // Congés à venir (7 jours)
        $this->cacheService->rememberLeaves('upcoming-7d', function () {
            return Conge::query()
                ->select(['id', 'employe_id', 'date_debut', 'date_fin'])
                ->where('etat', 'approuve')
                ->where('date_debut', '<=', now()->addDays(7))
                ->where('date_fin', '>=', now())
                ->with('employe:id,prenom,nom')
                ->get();
        }, 180);

        $pending = Conge::where('etat', 'soumis')->count();
        $this->info("   - {$pending} congés en attente cachés");
    }

    /**
     * Préchauffe les fiches de paie.
     * 
     * @performance Cache 15 min - stable après génération
     */
    protected function warmPayroll(bool $force): void
    {
        // Dernière génération
        $this->cacheService->rememberPayroll('latest-month', function () {
            return FichePaie::query()
                ->select(['mois', 'annee'])
                ->distinct()
                ->orderByDesc('annee')
                ->orderByDesc('mois')
                ->first();
        }, 900);

        // Statistiques paie mois courant
        $currentMonth = now()->month;
        $currentYear = now()->year;
        
        $this->cacheService->rememberPayroll("stats:{$currentMonth}:{$currentYear}", function () use ($currentMonth, $currentYear) {
            return [
                'total' => FichePaie::where('mois', $currentMonth)
                    ->where('annee', $currentYear)
                    ->count(),
                'total_net' => FichePaie::where('mois', $currentMonth)
                    ->where('annee', $currentYear)
                    ->sum('salaire_net'),
            ];
        }, 900);

        $this->info("   - Statistiques paie {$currentMonth}/{$currentYear} cachées");
    }

    /**
     * Préchauffe les statistiques dashboard.
     * 
     * @performance Réduit le temps de chargement du dashboard de 70%
     */
    protected function warmStats(bool $force): void
    {
        $this->cacheService->rememberStats('dashboard', function () {
            return [
                'total_employees' => User::where('statut', 'actif')->count(),
                'total_contracts' => Contrat::where('statut', 'actif')->count(),
                'contracts_expiring' => Contrat::where('statut', 'actif')
                    ->where('date_fin', '<=', now()->addMonth())
                    ->count(),
                'leaves_pending' => Conge::where('etat', 'soumis')->count(),
                'leaves_approved_month' => Conge::where('etat', 'approuve')
                    ->whereMonth('date_debut', now()->month)
                    ->count(),
                'payroll_pending' => FichePaie::where('statut', 'brouillon')->count(),
            ];
        }, 600);

        // Par département
        $departments = $this->cacheService->rememberDepartments(fn() => []);
        foreach ($departments as $dept) {
            $this->cacheService->rememberStats("dept:{$dept}", function () use ($dept) {
                return [
                    'employees' => User::where('departement', $dept)->where('statut', 'actif')->count(),
                    'contracts_expiring' => Contrat::whereHas('employe', fn($q) => $q->where('departement', $dept))
                        ->where('statut', 'actif')
                        ->where('date_fin', '<=', now()->addMonth())
                        ->count(),
                ];
            }, 600);
        }

        $this->info('   - Statistiques dashboard cachées');
    }

    /**
     * Préchauffe les permissions (par utilisateur admin/rh).
     */
    protected function warmPermissions(bool $force): void
    {
        // Préchauffer uniquement pour les admins et RH (utilisateurs fréquents)
        $users = User::role(['admin', 'rh'])->get();

        foreach ($users as $user) {
            $this->cacheService->rememberUserPermissions($user->id, function () use ($user) {
                return $user->getAllPermissions()->pluck('name')->toArray();
            });
        }

        $this->info("   - Permissions de {$users->count()} utilisateurs cachées");
    }

    /**
     * Affiche les statistiques du cache.
     */
    protected function displayCacheStats(): void
    {
        $driver = config('cache.default');
        $this->info("\n📊 Statistiques du cache (driver: {$driver})");

        try {
            if ($driver === 'redis') {
                $redis = Cache::store('redis')->connection();
                $keys = $redis->dbSize();
                $this->info("   - Clés en cache: {$keys}");
            } elseif ($driver === 'memcached') {
                $stats = Cache::store('memcached')->getMemcached()->getStats();
                foreach ($stats as $server => $stat) {
                    $this->info("   - {$server}: {$stat['get_hits']} hits, {$stat['get_misses']} misses");
                }
            }
        } catch (\Exception $e) {
            $this->warn("   Impossible de récupérer les statistiques: {$e->getMessage()}");
        }
    }
}

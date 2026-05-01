<?php

namespace App\Console\Commands;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmupCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hrmanager:warmup-cache {--clear : Vider le cache avant de pré-charger}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pré-charge toutes les données fréquemment utilisées en cache';

    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Démarrage du warmup cache...');

        if ($this->option('clear')) {
            $this->info('🧹 Vidage du cache existant...');
            $this->cacheService->flushAll();
            $this->info('✅ Cache vidé');
        }

        // 1. Cache des rôles
        $this->warmupRoles();

        // 2. Cache des permissions
        $this->warmupPermissions();

        // 3. Cache des employés actifs
        $this->warmupEmployes();

        // 4. Cache des contrats actifs
        $this->warmupContrats();

        // 5. Cache des congés en attente
        $this->warmupConges();

        $this->info('');
        $this->info('✅ Warmup cache terminé avec succès !');

        return self::SUCCESS;
    }

    /**
     * Pré-charge les rôles en cache.
     */
    private function warmupRoles(): void
    {
        $this->info('📦 Mise en cache des rôles...');
        
        try {
            $this->cacheService->cacheRoles(function () {
                return Role::with('permissions')->get();
            }, 3600);
            
            $count = Role::count();
            $this->info("   ✓ {$count} rôles mis en cache");
        } catch (\Exception $e) {
            $this->error("   ✗ Erreur lors du cache des rôles: {$e->getMessage()}");
            Log::error('Warmup cache rôles failed: ' . $e->getMessage());
        }
    }

    /**
     * Pré-charge les permissions en cache.
     */
    private function warmupPermissions(): void
    {
        $this->info('📦 Mise en cache des permissions...');
        
        try {
            $this->cacheService->cachePermissions(function () {
                return Permission::all()->groupBy('module');
            }, 3600);
            
            $count = Permission::count();
            $this->info("   ✓ {$count} permissions mises en cache");
        } catch (\Exception $e) {
            $this->error("   ✗ Erreur lors du cache des permissions: {$e->getMessage()}");
            Log::error('Warmup cache permissions failed: ' . $e->getMessage());
        }
    }

    /**
     * Pré-charge les employés actifs en cache.
     */
    private function warmupEmployes(): void
    {
        $this->info('📦 Mise en cache des employés...');
        
        try {
            $this->cacheService->cacheEmployes('actifs', function () {
                return User::actif()
                    ->with(['roles', 'contrats' => fn($q) => $q->actif()])
                    ->get();
            }, 1800);
            
            $count = User::actif()->count();
            $this->info("   ✓ {$count} employés actifs mis en cache");
        } catch (\Exception $e) {
            $this->error("   ✗ Erreur lors du cache des employés: {$e->getMessage()}");
            Log::error('Warmup cache employés failed: ' . $e->getMessage());
        }
    }

    /**
     * Pré-charge les contrats actifs en cache.
     */
    private function warmupContrats(): void
    {
        $this->info('📦 Mise en cache des contrats...');
        
        try {
            $this->cacheService->cacheContrats('actifs', function () {
                return Contrat::actif()
                    ->with('employe')
                    ->get();
            }, 1800);
            
            $count = Contrat::actif()->count();
            $this->info("   ✓ {$count} contrats actifs mis en cache");
            
            // Cache des contrats expirant bientôt
            $this->cacheService->cacheContrats('expirant_bientot', function () {
                return Contrat::expirantBientot(30)
                    ->with('employe')
                    ->get();
            }, 1800);
            
            $countExpirant = Contrat::expirantBientot(30)->count();
            $this->info("   ✓ {$countExpirant} contrats expirant bientôt mis en cache");
        } catch (\Exception $e) {
            $this->error("   ✗ Erreur lors du cache des contrats: {$e->getMessage()}");
            Log::error('Warmup cache contrats failed: ' . $e->getMessage());
        }
    }

    /**
     * Pré-charge les congés en attente en cache.
     */
    private function warmupConges(): void
    {
        $this->info('📦 Mise en cache des congés...');
        
        try {
            // Congés en attente
            $this->cacheService->cacheConges('en_attente', function () {
                return Conge::enAttente()
                    ->with(['employe', 'validations'])
                    ->get();
            }, 300);
            
            $countAttente = Conge::enAttente()->count();
            $this->info("   ✓ {$countAttente} congés en attente mis en cache");
            
            // Congés approuvés des 30 derniers jours
            $this->cacheService->cacheConges('approuves_30j', function () {
                return Conge::where('etat', 'approuve')
                    ->where('updated_at', '>=', now()->subDays(30))
                    ->with('employe')
                    ->get();
            }, 600);
            
            $countApprouves = Conge::where('etat', 'approuve')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count();
            $this->info("   ✓ {$countApprouves} congés approuvés (30j) mis en cache");
        } catch (\Exception $e) {
            $this->error("   ✗ Erreur lors du cache des congés: {$e->getMessage()}");
            Log::error('Warmup cache congés failed: ' . $e->getMessage());
        }
    }
}

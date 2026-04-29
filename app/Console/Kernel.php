<?php

namespace App\Console;

use App\Jobs\PurgeOldActivityLogsJob;
use App\Models\Contrat;
use App\Models\FichePaie;
use App\Modules\Contracts\Services\ContractAlertService;
use App\Services\DashboardCacheService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ==========================================
        // TÂCHES CRITIQUES - Quotidiennes
        // ==========================================
        
        // Génération des fiches de paie (1er du mois à 6h)
        $schedule->command('payroll:generate --month=' . now()->month . ' --year=' . now()->year)
            ->monthlyOn(1, '06:00')
            ->onOneServer()
            ->withoutOverlapping(3600)
            ->runInBackground();
        
        // Envoi des bulletins de paie (1er du mois à 9h)
        $schedule->command('payroll:send --month=' . now()->month)
            ->monthlyOn(1, '09:00')
            ->onOneServer()
            ->withoutOverlapping(1800);
        
        // ==========================================
        // ALERTES ET NOTIFICATIONS
        // ==========================================
        
        // Alertes contrats expirants (30, 60, 90 jours)
        $schedule->call(function () {
            app(ContractAlertService::class)->checkExpiringContracts();
        })
            ->dailyAt('08:00')
            ->onOneServer()
            ->withoutOverlapping(300)
            ->emailOutputOnFailure(config('mail.admin_address', 'admin@hrmanager.com'));
        
        // Alertes congés en attente (pour managers)
        $schedule->command('leaves:notify-pending')
            ->twiceDaily(9, 14)
            ->onOneServer();
        
        // Rappel validations (pour RH et Directeurs)
        $schedule->command('validations:send-reminders')
            ->dailyAt('10:00')
            ->onOneServer();
        
        // ==========================================
        // MAINTENANCE ET NETTOYAGE
        // ==========================================
        
        // Purge des logs d'activité (plus de 1 an)
        $schedule->job(new PurgeOldActivityLogsJob(365))
            ->monthlyOn(15, '02:00')
            ->onOneServer()
            ->withoutOverlapping(600);
        
        // Nettoyage des sessions expirées
        $schedule->command('session:gc')
            ->dailyAt('03:00')
            ->onOneServer();
        
        // Nettoyage des notifications lues (plus de 6 mois)
        $schedule->call(function () {
            \App\Models\Notification::where('read', true)
                ->where('created_at', '<', now()->subMonths(6))
                ->delete();
        })
            ->weeklyOn(0, '04:00') // Dimanche
            ->onOneServer();
        
        // Purge des fichiers temporaires
        $schedule->command('storage:purge-temp')
            ->dailyAt('04:30')
            ->onOneServer();
        
        // ==========================================
        // STATISTIQUES ET CACHE
        // ==========================================
        
        // Calcul des statistiques dashboard (toutes les heures en journée)
        $schedule->call(function () {
            app(DashboardCacheService::class)->warmDashboardCache();
        })
            ->hourlyBetween(8, 18)
            ->onOneServer()
            ->withoutOverlapping(300);
        
        // Mise à jour des soldes de congés (1er janvier)
        $schedule->command('leaves:reset-annual-balance')
            ->yearlyOn(1, 1, '00:01')
            ->onOneServer()
            ->withoutOverlapping(3600);
        
        // Précalcul des KPIs (une fois par jour)
        $schedule->command('reports:calculate-kpis')
            ->dailyAt('07:00')
            ->onOneServer();
        
        // ==========================================
        // SAUVEGARDES
        // ==========================================
        
        // Backup base de données (tous les jours à 1h)
        $schedule->command('backup:run --only-db')
            ->dailyAt('01:00')
            ->onOneServer()
            ->withoutOverlapping(1800);
        
        // Backup fichiers (hebdomadaire)
        $schedule->command('backup:run')
            ->weeklyOn(0, '02:00') // Dimanche
            ->onOneServer()
            ->withoutOverlapping(3600);
        
        // Nettoyage des vieux backups
        $schedule->command('backup:clean')
            ->weeklyOn(0, '05:00')
            ->onOneServer();
        
        // ==========================================
        // MONITORING
        // ==========================================
        
        // Health check (heartbeat)
        $schedule->command('monitor:heartbeat')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(60);
        
        // Rapport hebdomadaire aux admins
        $schedule->command('reports:weekly-summary')
            ->weeklyOn(1, '08:00') // Lundi matin
            ->onOneServer()
            ->withoutOverlapping(300)
            ->emailOutputOnFailure(config('mail.admin_address', 'admin@hrmanager.com'));
        
        // ==========================================
        // TÂCHES SPÉCIALES
        // ==========================================
        
        // Synchronisation avec système externe (si configuré)
        if (config('services.external_api.enabled')) {
            $schedule->command('sync:external-employees')
                ->dailyAt('06:30')
                ->onOneServer()
                ->withoutOverlapping(1800);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class CleanupOldNotifications extends Command
{
    protected $signature = 'notifications:cleanup 
                            {--days=90 : Nombre de jours avant suppression des notifications lues}
                            {--dry-run : Simulation sans suppression}';

    protected $description = 'Nettoie les vieilles notifications lues (plus de X jours)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Recherche des notifications lues datant de plus de {$days} jours...");

        $query = Notification::lues()
            ->where('read_at', '<', now()->subDays($days));

        $count = $query->count();

        if ($count === 0) {
            $this->info('Aucune notification à nettoyer.');
            return self::SUCCESS;
        }

        $this->info("{$count} notification(s) trouvée(s).");

        if ($dryRun) {
            $this->warn('Mode simulation - aucune suppression effectuée.');
            return self::SUCCESS;
        }

        $deleted = Notification::nettoyerVieilles();
        $this->info("{$deleted} notification(s) supprimée(s).");

        return self::SUCCESS;
    }
}

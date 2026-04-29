<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job pour purger les logs d'activité de plus d'un an.
 * S'exécute en arrière-plan pour ne pas bloquer l'application.
 */
class PurgeOldActivityLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $retentionDays;

    /**
     * Create a new job instance.
     *
     * @param int $retentionDays Nombre de jours de rétention (défaut: 365)
     */
    public function __construct(int $retentionDays = 365)
    {
        $this->retentionDays = $retentionDays;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $count = ActivityLog::purgeOldLogs($this->retentionDays);
        
        Log::info("Purge des logs d'activité: {$count} logs supprimés (plus de {$this->retentionDays} jours)");
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Échec de la purge des logs d\'activité: ' . $exception->getMessage());
    }
}

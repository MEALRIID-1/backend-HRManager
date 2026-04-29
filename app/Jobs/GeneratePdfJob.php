<?php

namespace App\Jobs;

use App\Models\FichePaie;
use App\Services\PdfGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job pour générer les PDFs en arrière-plan.
 * 
 * @performance Libère le worker HTTP pour les requêtes utilisateurs
 * @performance Peut traiter des milliers de PDFs en parallèle via workers
 */
class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120]; // Backoff exponentiel

    protected int $fichePaieId;
    protected array $options;

    /**
     * Tag de queue pour priorisation.
     */
    public $queue = 'pdf'; // Queue dédiée aux PDFs

    public function __construct(int $fichePaieId, array $options = [])
    {
        $this->fichePaieId = $fichePaieId;
        $this->options = $options;
    }

    public function handle(PdfGenerationService $pdfService): void
    {
        $fichePaie = FichePaie::with([
            'employe:id,prenom,nom,email,departement',
            'employe.contratActif:id,employe_id,salaire_base',
        ])->find($this->fichePaieId);

        if (!$fichePaie) {
            Log::warning("[PDF Job] FichePaie #{$this->fichePaieId} non trouvée");
            return;
        }

        try {
            $startTime = microtime(true);

            $pdfPath = $pdfService->generatePayslipPdf($fichePaie, $this->options);
            
            // Mettre à jour la fiche avec le chemin du PDF
            $fichePaie->update([
                'pdf_path' => $pdfPath,
                'pdf_generated_at' => now(),
            ]);

            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info("[PDF Job] PDF généré pour FichePaie #{$this->fichePaieId} en {$duration}s");

        } catch (\Exception $e) {
            Log::error("[PDF Job] Erreur génération PDF #{$this->fichePaieId}: " . $e->getMessage());
            throw $e; // Relancer pour retry
        }
    }

    /**
     * Job échoué après tous les retries.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[PDF Job] Échec définitif FichePaie #{$this->fichePaieId}: " . $exception->getMessage());
        
        // Notification admin
        // Notification::route('mail', config('mail.admin_address'))
        //     ->notify(new PdfGenerationFailed($this->fichePaieId, $exception));
    }
}

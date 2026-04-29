<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\FichePaie;
use App\Services\ActivityLogService;

/**
 * Observer pour le modèle FichePaie.
 * Log toutes les modifications sur les fiches de paie (données sensibles masquées).
 */
class FichePaieObserver
{
    private ActivityLogService $logService;

    public function __construct(ActivityLogService $logService)
    {
        $this->logService = $logService;
    }

    public function created(FichePaie $fichePaie): void
    {
        $this->logService->log(
            ActivityLog::ACTION_CREATED,
            $fichePaie,
            [],
            $this->getChanges($fichePaie),
            "Fiche de paie générée pour {$fichePaie->periode}"
        );
    }

    public function updated(FichePaie $fichePaie): void
    {
        if ($fichePaie->wasChanged()) {
            $this->logService->log(
                ActivityLog::ACTION_UPDATED,
                $fichePaie,
                $this->getOriginal($fichePaie),
                $this->getChanges($fichePaie),
                $this->getDescription($fichePaie)
            );
        }
    }

    public function deleted(FichePaie $fichePaie): void
    {
        $this->logService->log(
            ActivityLog::ACTION_DELETED,
            $fichePaie,
            $this->getOriginal($fichePaie),
            []
        );
    }

    public function restored(FichePaie $fichePaie): void
    {
        $this->logService->log(
            ActivityLog::ACTION_RESTORED,
            $fichePaie,
            [],
            $this->getChanges($fichePaie)
        );
    }

    /**
     * Masque les montants sensibles dans les logs.
     */
    private function maskSensitive(array $data): array
    {
        $sensitiveKeys = ['salaire_brut', 'salaire_net', 'cotisation_retraite', 
                          'cotisation_securite_sociale', 'cotisation_chomage', 
                          'csg_crd', 'autres_deductions'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[MASQUÉ]';
            }
        }
        return $data;
    }

    private function getChanges(FichePaie $fichePaie): array
    {
        $changes = [];
        foreach ($fichePaie->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $changes[$key] = $value;
            }
        }
        return $this->maskSensitive($changes);
    }

    private function getOriginal(FichePaie $fichePaie): array
    {
        $original = [];
        foreach ($fichePaie->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $original[$key] = $fichePaie->getOriginal($key);
            }
        }
        return $this->maskSensitive($original);
    }

    private function getDescription(FichePaie $fichePaie): ?string
    {
        if ($fichePaie->wasChanged('salaire_net')) {
            return "Modification du salaire net";
        }

        if ($fichePaie->wasChanged('date_paie')) {
            $oldDate = $fichePaie->getOriginal('date_paie');
            $newDate = $fichePaie->date_paie;
            return "Changement de date de paie: {$oldDate} → {$newDate}";
        }

        return null;
    }
}

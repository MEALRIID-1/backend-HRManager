<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Conge;
use App\Services\ActivityLogService;

/**
 * Observer pour le modèle Conge.
 * Log toutes les modifications sur les demandes de congé avec workflow tracking.
 */
class CongeObserver
{
    private ActivityLogService $logService;

    public function __construct(ActivityLogService $logService)
    {
        $this->logService = $logService;
    }

    public function created(Conge $conge): void
    {
        $this->logService->log(
            ActivityLog::ACTION_CREATED,
            $conge,
            [],
            $this->getChanges($conge)
        );
    }

    public function updated(Conge $conge): void
    {
        if ($conge->wasChanged()) {
            $action = $this->determineAction($conge);

            $this->logService->log(
                $action,
                $conge,
                $this->getOriginal($conge),
                $this->getChanges($conge),
                $this->getDescription($conge, $action)
            );
        }
    }

    public function deleted(Conge $conge): void
    {
        $this->logService->log(
            ActivityLog::ACTION_DELETED,
            $conge,
            $this->getOriginal($conge),
            []
        );
    }

    public function restored(Conge $conge): void
    {
        $this->logService->log(
            ActivityLog::ACTION_RESTORED,
            $conge,
            [],
            $this->getChanges($conge)
        );
    }

    private function determineAction(Conge $conge): string
    {
        if ($conge->wasChanged('etat')) {
            $newEtat = $conge->etat;

            return match ($newEtat) {
                'soumis' => ActivityLog::ACTION_SUBMITTED,
                'approuve' => ActivityLog::ACTION_APPROVED,
                'refuse_manager', 'refuse_rh', 'refuse_directeur' => ActivityLog::ACTION_REJECTED,
                'annule' => ActivityLog::ACTION_DELETED,
                default => ActivityLog::ACTION_UPDATED,
            };
        }

        return ActivityLog::ACTION_UPDATED;
    }

    private function getChanges(Conge $conge): array
    {
        $changes = [];
        foreach ($conge->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $changes[$key] = $value;
            }
        }
        return $changes;
    }

    private function getOriginal(Conge $conge): array
    {
        $original = [];
        foreach ($conge->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $original[$key] = $conge->getOriginal($key);
            }
        }
        return $original;
    }

    private function getDescription(Conge $conge, string $action): ?string
    {
        if ($conge->wasChanged('etat')) {
            $oldEtat = $conge->getOriginal('etat');
            $newEtat = $conge->etat;

            $etats = [
                'brouillon' => 'Brouillon',
                'soumis' => 'Soumis',
                'valide_manager' => 'Validé Manager',
                'valide_rh' => 'Validé RH',
                'approuve' => 'Approuvé',
                'refuse_manager' => 'Refusé Manager',
                'refuse_rh' => 'Refusé RH',
                'refuse_directeur' => 'Refusé Directeur',
                'annule' => 'Annulé',
            ];

            return "Transition workflow: {$etats[$oldEtat] ?? $oldEtat} → {$etats[$newEtat] ?? $newEtat}";
        }

        return null;
    }
}

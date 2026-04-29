<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Contrat;
use App\Services\ActivityLogService;

/**
 * Observer pour le modèle Contrat.
 * Log toutes les modifications sur les contrats.
 */
class ContratObserver
{
    private ActivityLogService $logService;

    public function __construct(ActivityLogService $logService)
    {
        $this->logService = $logService;
    }

    public function created(Contrat $contrat): void
    {
        $this->logService->log(
            ActivityLog::ACTION_CREATED,
            $contrat,
            [],
            $this->getChanges($contrat)
        );
    }

    public function updated(Contrat $contrat): void
    {
        if ($contrat->wasChanged()) {
            $action = $contrat->wasChanged('etat') && $contrat->etat === 'termine'
                ? ActivityLog::ACTION_TERMINATED
                : ActivityLog::ACTION_UPDATED;

            $this->logService->log(
                $action,
                $contrat,
                $this->getOriginal($contrat),
                $this->getChanges($contrat),
                $this->getDescription($contrat)
            );
        }
    }

    public function deleted(Contrat $contrat): void
    {
        $this->logService->log(
            ActivityLog::ACTION_DELETED,
            $contrat,
            $this->getOriginal($contrat),
            []
        );
    }

    public function restored(Contrat $contrat): void
    {
        $this->logService->log(
            ActivityLog::ACTION_RESTORED,
            $contrat,
            [],
            $this->getChanges($contrat)
        );
    }

    private function getChanges(Contrat $contrat): array
    {
        $changes = [];
        foreach ($contrat->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $changes[$key] = $value;
            }
        }
        return $changes;
    }

    private function getOriginal(Contrat $contrat): array
    {
        $original = [];
        foreach ($contrat->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $original[$key] = $contrat->getOriginal($key);
            }
        }
        return $original;
    }

    private function getDescription(Contrat $contrat): ?string
    {
        if ($contrat->wasChanged('etat')) {
            $oldEtat = $contrat->getOriginal('etat');
            $newEtat = $contrat->etat;
            return "Changement d'état: {$oldEtat} → {$newEtat}";
        }

        if ($contrat->wasChanged('salaire')) {
            return "Modification du salaire";
        }

        return null;
    }
}

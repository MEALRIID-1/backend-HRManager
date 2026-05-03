<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Contrat;
use App\Services\ActivityLogService;

class ContratObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Handle the Contrat "created" event.
     */
    public function created(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'create',
            'contrats',
            "Contrat créé pour l'employé #{$contrat->user_id} de type {$contrat->type}",
            $contrat->id,
            'Contrat',
            null,  // oldValues
            null,  // newValues
            null   // userName (optionnel)
        );
    }

    /**
     * Handle the Contrat "updated" event.
     */
    public function updated(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'update',
            'contrats',
            "Contrat #{$contrat->id} modifié",
            $contrat->id,
            'Contrat',
            null,  // oldValues
            null,  // newValues
            null   // userName
        );
    }

    /**
     * Handle the Contrat "deleted" event.
     */
    public function deleted(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'delete',
            'contrats',
            "Contrat #{$contrat->id} supprimé",
            $contrat->id,
            'Contrat',
            null,  // oldValues
            null,  // newValues
            null   // userName
        );
    }

    /**
     * Handle the Contrat "restored" event.
     */
    public function restored(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'restore',
            'contrats',
            "Contrat #{$contrat->id} restauré",
            $contrat->id,
            'Contrat',
            null,  // oldValues
            null,  // newValues
            null   // userName
        );
    }
}
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
            'contrat',
            null,
            $contrat->toArray()
        );
    }

    /**
     * Handle the Contrat "updated" event.
     */
    public function updated(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'update',
            'contrat',
            $contrat->getOriginal(),
            $contrat->toArray()
        );
    }

    /**
     * Handle the Contrat "deleted" event.
     */
    public function deleted(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'delete',
            'contrat',
            $contrat->toArray(),
            null
        );
    }

    /**
     * Handle the Contrat "restored" event.
     */
    public function restored(Contrat $contrat): void
    {
        $this->activityLogService->log(
            'restore',
            'contrat',
            null,
            $contrat->toArray()
        );
    }
}

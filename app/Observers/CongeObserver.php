<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Conge;
use App\Services\ActivityLogService;

class CongeObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Handle the Conge "created" event.
     */
    public function created(Conge $conge): void
    {
        $this->activityLogService->log(
            'create',
            'conge',
            null,
            $conge->toArray()
        );
    }

    /**
     * Handle the Conge "updated" event.
     */
    public function updated(Conge $conge): void
    {
        $this->activityLogService->log(
            'update',
            'conge',
            $conge->getOriginal(),
            $conge->toArray()
        );
    }

    /**
     * Handle the Conge "deleted" event.
     */
    public function deleted(Conge $conge): void
    {
        $this->activityLogService->log(
            'delete',
            'conge',
            $conge->toArray(),
            null
        );
    }

    /**
     * Handle the Conge "restored" event.
     */
    public function restored(Conge $conge): void
    {
        $this->activityLogService->log(
            'restore',
            'conge',
            null,
            $conge->toArray()
        );
    }
}

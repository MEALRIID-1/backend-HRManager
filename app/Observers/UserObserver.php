<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->activityLogService->log(
            'create',
            'user',
            null,
            $user->toArray()
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->activityLogService->log(
            'update',
            'user',
            $user->getOriginal(),
            $user->toArray()
        );
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->activityLogService->log(
            'delete',
            'user',
            $user->toArray(),
            null
        );
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->activityLogService->log(
            'restore',
            'user',
            null,
            $user->toArray()
        );
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        $this->activityLogService->log(
            'force_delete',
            'user',
            $user->toArray(),
            null
        );
    }
}

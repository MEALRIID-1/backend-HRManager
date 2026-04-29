<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;

/**
 * Observer pour le modèle User.
 * Log toutes les modifications sur les utilisateurs (sauf les champs sensibles).
 */
class UserObserver
{
    private ActivityLogService $logService;

    public function __construct(ActivityLogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->logService->log(
            ActivityLog::ACTION_CREATED,
            $user,
            [],
            $this->getChanges($user)
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged()) {
            $this->logService->log(
                ActivityLog::ACTION_UPDATED,
                $user,
                $this->getOriginal($user),
                $this->getChanges($user)
            );
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->logService->log(
            ActivityLog::ACTION_DELETED,
            $user,
            $this->getOriginal($user),
            []
        );
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->logService->log(
            ActivityLog::ACTION_RESTORED,
            $user,
            [],
            $this->getChanges($user)
        );
    }

    /**
     * Récupère les changements sans les champs sensibles.
     */
    private function getChanges(User $user): array
    {
        $changes = [];
        foreach ($user->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $changes[$key] = $value;
            } else {
                $changes[$key] = '[REDACTED]';
            }
        }
        return $changes;
    }

    /**
     * Récupère les valeurs originales sans les champs sensibles.
     */
    private function getOriginal(User $user): array
    {
        $original = [];
        foreach ($user->getDirty() as $key => $value) {
            if (!in_array($key, ActivityLog::$sensitiveFields)) {
                $original[$key] = $user->getOriginal($key);
            } else {
                $original[$key] = '[REDACTED]';
            }
        }
        return $original;
    }
}

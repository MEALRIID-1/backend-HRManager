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
        // ✅ Description en string, pas en tableau
        $this->activityLogService->log(
            'create',
            'conge',
            "Demande de congé créée (#{$conge->id}) du {$conge->date_debut} au {$conge->date_fin}",
            $conge->id,
            'App\Models\Conge',
            auth()->id(),
            auth()->user()?->prenom . ' ' . auth()->user()?->nom,
            request()->ip(),
            request()->userAgent(),
        );
    }

    /**
     * Handle the Conge "updated" event.
     */
    public function updated(Conge $conge): void
    {
        // ✅ Récupérer les changements en string
        $changes = [];
        $original = $conge->getOriginal();
        
        foreach ($conge->getChanges() as $field => $newValue) {
            $oldValue = $original[$field] ?? null;
            if (!in_array($field, ['updated_at', 'created_at', 'deleted_at'])) {
                $changes[] = "{$field}: '{$oldValue}' → '{$newValue}'";
            }
        }
        
        $description = "Demande de congé #{$conge->id} modifiée";
        if (!empty($changes)) {
            $description .= " (" . implode(', ', $changes) . ")";
        }
        
        $this->activityLogService->log(
            'update',
            'conge',
            $description,
            $conge->id,
            'App\Models\Conge',
            auth()->id(),
            auth()->user()?->prenom . ' ' . auth()->user()?->nom,
            request()->ip(),
            request()->userAgent(),
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
            "Demande de congé #{$conge->id} supprimée",
            $conge->id,
            'App\Models\Conge',
            auth()->id(),
            auth()->user()?->prenom . ' ' . auth()->user()?->nom,
            request()->ip(),
            request()->userAgent(),
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
            "Demande de congé #{$conge->id} restaurée",
            $conge->id,
            'App\Models\Conge',
            auth()->id(),
            auth()->user()?->prenom . ' ' . auth()->user()?->nom,
            request()->ip(),
            request()->userAgent(),
        );
    }
}
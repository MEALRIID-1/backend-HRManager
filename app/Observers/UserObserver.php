<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->activityLogService->log(
            action: 'create',
            module: 'user',
            description: "Utilisateur {$user->prenom} {$user->nom} a été créé",
            referenceId: $user->id,
            referenceType: 'App\Models\User',
            userId: auth()->id(),
            userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Récupérer uniquement les champs modifiés
        $changes = [];
        $original = $user->getOriginal();
        
        foreach ($user->getChanges() as $field => $newValue) {
            // Ignorer les timestamps
            if (in_array($field, ['updated_at', 'created_at', 'deleted_at'])) {
                continue;
            }
            
            $oldValue = $original[$field] ?? null;
            
            // Masquer le mot de passe dans les logs
            if ($field === 'mot_de_passe') {
                $oldValue = '***';
                $newValue = '***';
            }
            
            $changes[] = "{$field}: '" . ($oldValue ?? 'null') . "' → '" . ($newValue ?? 'null') . "'";
        }
        
        $description = "Utilisateur {$user->prenom} {$user->nom} a été modifié";
        if (!empty($changes)) {
            $description .= " (" . implode(', ', $changes) . ")";
        }
        
        $this->activityLogService->log(
            action: 'update',
            module: 'user',
            description: $description,
            referenceId: $user->id,
            referenceType: 'App\Models\User',
            userId: auth()->id(),
            userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
        );
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->activityLogService->log(
            action: 'delete',
            module: 'user',
            description: "Utilisateur {$user->prenom} {$user->nom} a été supprimé",
            referenceId: $user->id,
            referenceType: 'App\Models\User',
            userId: auth()->id(),
            userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
        );
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->activityLogService->log(
            action: 'restore',
            module: 'user',
            description: "Utilisateur {$user->prenom} {$user->nom} a été restauré",
            referenceId: $user->id,
            referenceType: 'App\Models\User',
            userId: auth()->id(),
            userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
        );
    }

    /**
     * Handle the User "forceDeleted" event.
     */
    public function forceDeleted(User $user): void
    {
        $this->activityLogService->log(
            action: 'force_delete',
            module: 'user',
            description: "Utilisateur {$user->prenom} {$user->nom} a été supprimé définitivement",
            referenceId: $user->id,
            referenceType: 'App\Models\User',
            userId: auth()->id(),
            userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
        );
    }
}
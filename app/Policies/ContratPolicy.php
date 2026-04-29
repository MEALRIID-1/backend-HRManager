<?php

namespace App\Policies;

use App\Models\Contrat;
use App\Models\User;

class ContratPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view contracts') || $user->hasRole(['rh', 'admin']);
    }

    public function view(User $user, Contrat $contrat): bool
    {
        // RH et Admin peuvent tout voir
        if ($user->can('view contracts') || $user->hasRole(['rh', 'admin'])) {
            return true;
        }

        // L'employé peut voir ses propres contrats
        if ($user->id === $contrat->employe_id) {
            return true;
        }

        // Le manager peut voir les contrats de son équipe
        if ($user->hasRole('manager') && $contrat->employe->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Seul RH et Admin peuvent créer
        return $user->can('create-contracts') || $user->hasRole(['rh', 'admin']);
    }

    public function update(User $user, Contrat $contrat): bool
    {
        // Seul RH et Admin peuvent modifier
        return $user->can('edit-contracts') || $user->hasRole(['rh', 'admin']);
    }

    public function delete(User $user, Contrat $contrat): bool
    {
        // Seul Admin peut supprimer
        return $user->can('delete-contracts') || $user->hasRole('admin');
    }

    public function terminate(User $user, Contrat $contrat): bool
    {
        // RH et Admin peuvent résilier
        return $user->can('terminate-contracts') || $user->hasRole(['rh', 'admin']);
    }
}

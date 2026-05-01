<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contrat;
use App\Models\User;

class ContratPolicy
{
    /**
     * Déterminer si l'utilisateur peut voir tous les contrats.
     */
    public function viewAny(User $user): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut voir un contrat spécifique.
     */
    public function view(User $user, Contrat $contrat): bool
    {
        // Admin et RH voient tout
        if ($user->roles->contains('slug', 'admin') || $user->roles->contains('slug', 'rh')) {
            return true;
        }

        // Manager voit les contrats de son département
        if ($user->roles->contains('slug', 'manager')) {
            return $contrat->employe->departement === $user->departement;
        }

        // Employé ne voit que son propre contrat
        return $contrat->employe_id === $user->id;
    }

    /**
     * Déterminer si l'utilisateur peut créer un contrat.
     * Seuls admin et RH peuvent créer.
     */
    public function create(User $user): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut mettre à jour un contrat.
     */
    public function update(User $user, Contrat $contrat): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut supprimer un contrat.
     */
    public function delete(User $user, Contrat $contrat): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut imprimer un contrat.
     * Admin/RH seulement.
     */
    public function imprimer(User $user, Contrat $contrat): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut télécharger un contrat.
     * Admin/RH seulement.
     */
    public function telecharger(User $user, Contrat $contrat): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut restaurer un contrat supprimé.
     */
    public function restore(User $user, Contrat $contrat): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut voir les contrats supprimés.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->roles->contains('slug', 'admin');
    }
}

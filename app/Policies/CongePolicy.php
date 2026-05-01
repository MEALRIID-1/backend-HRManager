<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conge;
use App\Models\User;

class CongePolicy
{
    /**
     * Déterminer si l'utilisateur peut voir tous les congés.
     */
    public function viewAny(User $user): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut voir un congé spécifique.
     * Admin/RH voient tout, manager voit son équipe, employé voit les siens.
     */
    public function view(User $user, Conge $conge): bool
    {
        // Admin et RH voient tout
        if ($user->roles->contains('slug', 'admin') || $user->roles->contains('slug', 'rh')) {
            return true;
        }

        // Manager voit les congés de son département
        if ($user->roles->contains('slug', 'manager')) {
            return $conge->employe->departement === $user->departement;
        }

        // Employé ne voit que ses propres congés
        return $conge->employe_id === $user->id;
    }

    /**
     * Déterminer si l'utilisateur peut créer un congé.
     * Tous les employés actifs peuvent créer.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Déterminer si l'utilisateur peut mettre à jour un congé.
     * Seulement l'auteur et seulement si en_attente.
     */
    public function update(User $user, Conge $conge): bool
    {
        // Seul l'auteur peut modifier
        if ($conge->employe_id !== $user->id) {
            return false;
        }

        // Seulement si le congé est en attente
        return $conge->etat === 'en_attente';
    }

    /**
     * Déterminer si l'utilisateur peut supprimer un congé.
     * Seulement l'auteur et seulement si en_attente.
     */
    public function delete(User $user, Conge $conge): bool
    {
        // Admin peut tout supprimer
        if ($user->roles->contains('slug', 'admin')) {
            return true;
        }

        // Seul l'auteur peut supprimer
        if ($conge->employe_id !== $user->id) {
            return false;
        }

        // Seulement si le congé est en attente
        return $conge->etat === 'en_attente';
    }

    /**
     * Déterminer si l'utilisateur peut valider un congé.
     * Selon le rôle : N1=manager, N2=rh, N3=admin.
     */
    public function valider(User $user, Conge $conge): bool
    {
        // Récupérer le niveau de validation du congé
        $niveauConge = $conge->niveau_validation;

        // Niveau 0 (nouveau) : Manager (N1), RH (N2) et Admin (N3) peuvent valider
        if ($niveauConge === 0) {
            return $user->roles->contains('slug', 'manager') ||
                   $user->roles->contains('slug', 'rh') ||
                   $user->roles->contains('slug', 'admin');
        }

        // Niveau 1 (validé N1) : RH (N2) et Admin (N3) peuvent valider
        if ($niveauConge === 1) {
            return $user->roles->contains('slug', 'rh') ||
                   $user->roles->contains('slug', 'admin');
        }

        // Niveau 2 (validé N2) : Seul Admin (N3) peut valider
        if ($niveauConge === 2) {
            return $user->roles->contains('slug', 'admin');
        }

        return false;
    }

    /**
     * Déterminer si l'utilisateur peut faire une super validation.
     * Admin seulement - bypass le workflow complet.
     */
    public function superValidation(User $user): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut refuser un congé.
     * Mêmes règles que valider.
     */
    public function refuser(User $user, Conge $conge): bool
    {
        return $this->valider($user, $conge);
    }

    /**
     * Déterminer si l'utilisateur peut restaurer un congé supprimé.
     */
    public function restore(User $user, Conge $conge): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut voir les congés supprimés.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->roles->contains('slug', 'admin');
    }
}

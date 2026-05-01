<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EmployePolicy
{
    /**
     * Déterminer si l'utilisateur peut voir tous les employés.
     */
    public function viewAny(User $user): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh') ||
               $user->roles->contains('slug', 'manager');
    }

    /**
     * Déterminer si l'utilisateur peut voir un employé spécifique.
     * Admin/RH voient tout, manager filtré sur son département.
     */
    public function view(User $user, User $employe): bool
    {
        // Admin voit tout
        if ($user->roles->contains('slug', 'admin')) {
            return true;
        }

        // RH voit tout
        if ($user->roles->contains('slug', 'rh')) {
            return true;
        }

        // Manager voit uniquement son département
        if ($user->roles->contains('slug', 'manager')) {
            return $employe->departement === $user->departement;
        }

        // Employé ne voit que son propre profil
        return $user->id === $employe->id;
    }

    /**
     * Déterminer si l'utilisateur peut créer un employé.
     * Seuls admin et RH peuvent créer.
     */
    public function create(User $user): bool
    {
        return $user->roles->contains('slug', 'admin') || 
               $user->roles->contains('slug', 'rh');
    }

    /**
     * Déterminer si l'utilisateur peut mettre à jour un employé.
     */
    public function update(User $user, User $employe): bool
    {
        // Admin peut tout modifier
        if ($user->roles->contains('slug', 'admin')) {
            return true;
        }

        // RH peut modifier
        if ($user->roles->contains('slug', 'rh')) {
            return true;
        }

        // Un utilisateur peut modifier son propre profil (sauf certains champs sensibles)
        return $user->id === $employe->id;
    }

    /**
     * Déterminer si l'utilisateur peut supprimer un employé.
     */
    public function delete(User $user, User $employe): bool
    {
        // Seul admin peut supprimer
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut restaurer un employé supprimé.
     */
    public function restore(User $user, User $employe): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut voir les employés supprimés.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut gérer les permissions d'un employé.
     */
    public function managePermissions(User $user, User $employe): bool
    {
        return $user->roles->contains('slug', 'admin');
    }

    /**
     * Déterminer si l'utilisateur peut gérer les rôles d'un employé.
     */
    public function manageRoles(User $user, User $employe): bool
    {
        return $user->roles->contains('slug', 'admin');
    }
}

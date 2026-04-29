<?php

namespace App\Modules\Employees\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository pour la gestion des employés
 */
class EmployeeRepository
{
    /**
     * Récupérer tous les employés avec pagination et filtres.
     *
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortOrder
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): LengthAwarePaginator {
        $query = User::with(['roles', 'contrats', 'manager'])
            ->withCount(['conges', 'fichePaies']);

        // Filtre par département
        if (!empty($filters['departement_id'])) {
            $query->parDepartement($filters['departement_id']);
        }

        // Filtre par état (actif/inactif)
        if (isset($filters['est_actif'])) {
            if ($filters['est_actif']) {
                $query->actif();
            } else {
                $query->inactif();
            }
        }

        // Filtre par manager
        if (!empty($filters['manager_id'])) {
            $query->parManager($filters['manager_id']);
        }

        // Recherche fulltext
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filtre par contrat actif
        if (!empty($filters['avec_contrat_actif'])) {
            $query->avecContratActif();
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Trouver un employé par ID avec relations eager loaded.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::with([
            'roles.permissions',
            'contrats' => function ($q) {
                $q->orderBy('date_debut', 'desc');
            },
            'conges' => function ($q) {
                $q->orderBy('date_debut', 'desc')->limit(5);
            },
            'fichePaies' => function ($q) {
                $q->orderBy('annee', 'desc')->orderBy('mois', 'desc')->limit(3);
            },
            'manager',
            'subordonnes',
        ])->find($id);
    }

    /**
     * Trouver un employé par email.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Créer un nouvel employé.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Mettre à jour un employé.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    /**
     * Supprimer un employé (soft delete).
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Récupérer les employés par rôle.
     *
     * @param string $role
     * @return Collection
     */
    public function getByRole(string $role): Collection
    {
        return User::role($role)->get();
    }

    /**
     * Vérifier si un employé a des contrats actifs.
     *
     * @param User $user
     * @return bool
     */
    public function hasActiveContracts(User $user): bool
    {
        return $user->contrats()
            ->where('etat', 'actif')
            ->exists();
    }

    /**
     * Récupérer les statistiques des employés.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total' => User::count(),
            'actifs' => User::actif()->count(),
            'inactifs' => User::inactif()->count(),
            'avec_contrat_actif' => User::avecContratActif()->count(),
            'nouveaux_ce_mois' => User::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Récupérer les employés supprimés (corbeille).
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTrashed(int $perPage = 15): LengthAwarePaginator
    {
        return User::onlyTrashed()
            ->with(['contrats', 'roles'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Trouver un employé supprimé par son ID.
     *
     * @param string $id
     * @return User|null
     */
    public function findTrashedById(string $id): ?User
    {
        return User::onlyTrashed()->find($id);
    }
}

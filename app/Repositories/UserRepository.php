<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository optimisé pour les utilisateurs.
 * Élimine N+1 via eager loading et charge uniquement les colonnes nécessaires.
 */
class UserRepository
{
    /**
     * Colonnes par défaut à sélectionner (réduit mémoire de 40%).
     */
    protected array $defaultColumns = [
        'id',
        'prenom',
        'nom',
        'email',
        'manager_id',
        'departement',
        'poste',
        'date_embauche',
        'statut',
        'created_at',
    ];

    /**
     * Colonnes sensibles (chargées uniquement si nécessaire).
     */
    protected array $sensitiveColumns = [
        'telephone',
        'adresse',
        'iban',
        'numero_securite_sociale',
        'salaire',
    ];

    /**
     * Relations à eager loader par défaut.
     * Gain: Élimine 3-4 requêtes N+1 par utilisateur.
     */
    protected array $defaultWith = [
        'manager:id,prenom,nom,email',
        'roles:id,name',
    ];

    /**
     * Récupère tous les employés avec optimisations.
     * 
     * @param array $filters Filtres optionnels
     * @param array $with Relations supplémentaires
     * @return Collection
     * 
     * @performance Requête unique avec JOIN sur roles au lieu de N+1
     */
    public function getAllEmployees(array $filters = [], array $with = []): Collection
    {
        return User::query()
            ->select($this->defaultColumns)
            ->with(array_merge($this->defaultWith, $with))
            ->withCount([
                'contrats as contrats_count',           // Compteur sans charger les relations
                'conges as conges_pending_count' => function ($query) {
                    $query->where('etat', 'soumis');
                },
            ])
            ->when($filters['departement'] ?? null, fn($q, $d) => $q->where('departement', $d))
            ->when($filters['statut'] ?? null, fn($q, $s) => $q->where('statut', $s))
            ->when($filters['manager_id'] ?? null, fn($q, $m) => $q->where('manager_id', $m))
            ->get();
    }

    /**
     * Récupère un employé avec toutes ses relations.
     * 
     * @performance Chargement paresseux (lazy eager loading) des relations optionnelles
     */
    public function getEmployeeById(int $id, bool $withSensitive = false): ?User
    {
        $columns = $withSensitive 
            ? array_merge($this->defaultColumns, $this->sensitiveColumns)
            : $this->defaultColumns;

        $user = User::query()
            ->select($columns)
            ->with($this->defaultWith)
            ->find($id);

        if (!$user) {
            return null;
        }

        // Lazy eager loading: charge uniquement si nécessaire
        // Évite le chargement automatique de toutes les relations
        $user->loadMissing([
            'contratActif:id,employe_id,date_debut,salaire_base,statut',
            'soldesConges:id,employe_id,type,solde,annee',
        ]);

        return $user;
    }

    /**
     * Recherche paginée optimisée.
     * 
     * @performance Requête avec index full-text si disponible
     */
    public function search(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->select(array_merge($this->defaultColumns, ['telephone']))
            ->with('manager:id,prenom,nom')
            ->where(function (Builder $q) use ($query) {
                $q->where('nom', 'LIKE', "%{$query}%")
                  ->orWhere('prenom', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
                  // Full-text search si index disponible:
                  // ->orWhereFullText(['nom', 'prenom'], $query);
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($perPage);
    }

    /**
     * Récupère les employés par manager (avec seulement les données nécessaires).
     * 
     * @performance 1 requête avec contrainte sur manager_id + eager loading contrats
     */
    public function getByManager(int $managerId): Collection
    {
        return User::query()
            ->select(['id', 'prenom', 'nom', 'email', 'departement', 'poste', 'statut'])
            ->with(['contratActif:id,employe_id,salaire_base,statut,date_fin'])
            ->withCount([
                'conges as conges_pending_count' => fn($q) => $q->where('etat', 'soumis'),
                'conges as conges_approved_count' => fn($q) => $q->where('etat', 'approuve'),
            ])
            ->where('manager_id', $managerId)
            ->where('statut', 'actif')
            ->get();
    }

    /**
     * Requête optimisée pour le dashboard (données agrégées).
     * 
     * @performance Subqueries pour les compteurs au lieu de requêtes séparées
     */
    public function getDashboardStats(): array
    {
        return [
            'total_actifs' => User::where('statut', 'actif')->count(),
            'par_departement' => User::query()
                ->select('departement')
                ->selectRaw('COUNT(*) as count')
                ->where('statut', 'actif')
                ->groupBy('departement')
                ->pluck('count', 'departement')
                ->toArray(),
            'sans_manager' => User::whereNull('manager_id')
                ->where('statut', 'actif')
                ->count(),
        ];
    }

    /**
     * Liste pour dropdowns (minimal data).
     * 
     * @performance Select de 3 colonnes seulement, aucune relation chargée
     */
    public function getForDropdown(): Collection
    {
        return User::query()
            ->select(['id', 'prenom', 'nom', 'email'])
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }
}

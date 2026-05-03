<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    /**
     * Recherche globale dans employés, contrats et congés.
     *
     * @param string $query
     * @return array<string, mixed>
     */
    public function searchGlobal(string $query): array
    {
        $results = [
            'employes' => $this->searchEmployes($query),
            'contrats' => $this->searchContrats($query),
            'conges' => $this->searchConges($query),
        ];

        return $results;
    }

    /**
     * Recherche dans les employés.
     *
     * @param string $query
     * @return Collection
     */
    public function searchEmployes(string $query): Collection
    {
        return User::query()
            ->actif()
            ->search($query)
            ->select(['id', 'nom', 'prenom', 'email', 'poste', 'departement'])
            ->limit(10)
            ->get();
    }

    /**
     * Recherche dans les contrats.
     *
     * @param string $query
     * @return Collection
     */
    public function searchContrats(string $query): Collection
    {
        return Contrat::query()
            ->where(function ($q) use ($query) {
                $q->where('type', 'LIKE', "%{$query}%")
                  ->orWhereHas('employe', function ($sq) use ($query) {
                      $sq->where('nom', 'LIKE', "%{$query}%")
                         ->orWhere('prenom', 'LIKE', "%{$query}%");
                  });
            })
            ->with(['employe:id,nom,prenom'])
            ->limit(10)
            ->get();
    }

    /**
     * Recherche dans les congés.
     *
     * @param string $query
     * @return Collection
     */
    public function searchConges(string $query): Collection
    {
        return Conge::query()
            ->where(function ($q) use ($query) {
                $q->where('type', 'LIKE', "%{$query}%")
                  ->orWhere('statut', 'LIKE', "%{$query}%")
                  ->orWhereHas('employe', function ($sq) use ($query) {
                      $sq->where('nom', 'LIKE', "%{$query}%")
                         ->orWhere('prenom', 'LIKE', "%{$query}%");
                  });
            })
            ->with(['employe:id,nom,prenom'])
            ->limit(10)
            ->get();
    }

    /**
     * Recherche paginée d'employés avec filtres.
     *
     * @param array<string, mixed> $filters
     * @param string|null $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchEmployesPaginated(array $filters = [], ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->actif();

        if ($search) {
            $query->search($search);
        }

        if (isset($filters['departement'])) {
            $query->byDepartement($filters['departement']);
        }

        if (isset($filters['role'])) {
            $query->byRole($filters['role']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Recherche paginée de congés avec filtres.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchCongesPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Conge::query();

        if (isset($filters['statut'])) {
            $query->byStatut($filters['statut']);
        }

        if (isset($filters['employe_id'])) {
            $query->byEmploye($filters['employe_id']);
        }

        if (isset($filters['date_debut']) && isset($filters['date_fin'])) {
            $query->byPeriode(
                \Carbon\Carbon::parse($filters['date_debut']),
                \Carbon\Carbon::parse($filters['date_fin'])
            );
        }

        return $query->with(['employe:id,nom,prenom'])
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }

    /**
     * Recherche paginée de contrats avec filtres.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchContratsPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Contrat::query();

        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['expirant_bientot']) && $filters['expirant_bientot']) {
            $jours = $filters['jours'] ?? 30;
            $query->expirantBientot($jours);
        }

        return $query->with(['employe:id,nom,prenom'])
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }
}

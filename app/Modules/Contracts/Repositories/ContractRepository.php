<?php

namespace App\Modules\Contracts\Repositories;

use App\Models\Contrat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContractRepository
{
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Contrat::with(['employe', 'avenants']);

        if (!empty($filters['etat'])) {
            $query->where('etat', $filters['etat']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['employe_id'])) {
            $query->where('employe_id', $filters['employe_id']);
        }

        if (!empty($filters['expirant_sous'])) {
            $query->expirantSous((int) $filters['expirant_sous']);
        }

        if (!empty($filters['en_periode_essai'])) {
            $query->enPeriodeEssai();
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function findById(int $id): ?Contrat
    {
        return Contrat::with(['employe', 'avenants.createur'])->find($id);
    }

    public function create(array $data): Contrat
    {
        return Contrat::create($data);
    }

    public function update(Contrat $contrat, array $data): Contrat
    {
        $contrat->update($data);
        return $contrat;
    }

    public function delete(Contrat $contrat): bool
    {
        // Sauvegarder l'état avant archivage
        $contrat->etat_avant_archivage = $contrat->etat;
        $contrat->etat = 'archive';
        $contrat->save();

        return $contrat->delete();
    }

    public function restore(Contrat $contrat): bool
    {
        // Restaurer l'état précédent
        if ($contrat->etat_avant_archivage) {
            $contrat->etat = $contrat->etat_avant_archivage;
            $contrat->etat_avant_archivage = null;
        }
        $contrat->save();

        return $contrat->restore();
    }

    public function getExpiringContracts(int $jours): Collection
    {
        return Contrat::with('employe')
            ->actifs()
            ->expirantSous($jours)
            ->get();
    }

    public function getActiveContractsForEmployee(int $employeId): Collection
    {
        return Contrat::parEmploye($employeId)->actifs()->get();
    }

    public function hasOverlappingContract(int $employeId, \Carbon\Carbon $dateDebut, ?\Carbon\Carbon $dateFin, ?int $excludeId = null): bool
    {
        $query = Contrat::parEmploye($employeId)
            ->whereNotIn('etat', [Contrat::ETAT_TERMINE]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()->contains(function ($contrat) use ($dateDebut, $dateFin) {
            return $contrat->chevauche($dateDebut, $dateFin);
        });
    }

    public function getStats(): array
    {
        return [
            'total' => Contrat::count(),
            'en_cours' => Contrat::enCours()->count(),
            'periode_essai' => Contrat::enPeriodeEssai()->count(),
            'suspendus' => Contrat::suspendus()->count(),
            'termines' => Contrat::termines()->count(),
            'expirant_sous_30' => Contrat::actifs()->expirantSous(30)->count(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Conge;
use App\Repositories\Contracts\CongeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class CongeRepository implements CongeRepositoryInterface
{
    public function findById(int $id): ?Conge
    {
        return Conge::find($id);
    }

    public function create(array $data): Conge
    {
        return Conge::create($data);
    }

    public function update(Conge $conge, array $data): Conge
    {
        $conge->update($data);
        return $conge;
    }

    public function delete(Conge $conge): bool
    {
        return $conge->delete();
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Conge::query();

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getEnAttente(): Collection
    {
        return Conge::where('statut', Conge::STATUT_EN_ATTENTE)->get();
    }

    public function getByUserId(int $userId): Collection
    {
        return Conge::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
    }

    public function getSoldeConges(int $userId, int $annee): array
    {
        $debutAnnee = Carbon::create($annee, 1, 1);
        $finAnnee = Carbon::create($annee, 12, 31);

        $totalAccorde = 25;
        $joursPris = Conge::where('user_id', $userId)
            ->where('statut', Conge::STATUT_APPROUVE)
            ->where('type', Conge::TYPE_CONGE_PAYE)
            ->whereBetween('date_debut', [$debutAnnee, $finAnnee])
            ->sum('nombre_jours');

        $joursEnAttente = Conge::where('user_id', $userId)
            ->where('statut', Conge::STATUT_EN_ATTENTE)
            ->where('type', Conge::TYPE_CONGE_PAYE)
            ->whereBetween('date_debut', [$debutAnnee, $finAnnee])
            ->sum('nombre_jours');

        return [
            'annee' => $annee,
            'total_accorde' => $totalAccorde,
            'jours_pris' => $joursPris,
            'jours_en_attente' => $joursEnAttente,
            'solde_restant' => $totalAccorde - $joursPris,
        ];
    }

    public function countByStatut(string $statut): int
    {
        return Conge::where('statut', $statut)->count();
    }
}

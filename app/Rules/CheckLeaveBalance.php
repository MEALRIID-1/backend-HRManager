<?php

namespace App\Rules;

use App\Models\Conge;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de validation pour vérifier que l'employé a suffisamment de solde de congés.
 */
class CheckLeaveBalance implements ValidationRule
{
    private int $employeId;
    private ?int $excludeCongeId;

    public function __construct(int $employeId, ?int $excludeCongeId = null)
    {
        $this->employeId = $employeId;
        $this->excludeCongeId = $excludeCongeId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = request()->input('type');
        $dateDebut = request()->input('date_debut');
        $dateFin = request()->input('date_fin');

        if (!$type || !$dateDebut || !$dateFin) {
            return;
        }

        // Types sans solde (toujours valides)
        $typesSansSolde = [
            Conge::TYPE_CONGE_SANS_SOLDE,
            Conge::TYPE_MALADIE,
            Conge::TYPE_MATERNITE,
            Conge::TYPE_PATERNITE,
        ];

        if (in_array($type, $typesSansSolde)) {
            return;
        }

        // Calculer le solde disponible
        $annee = Carbon::parse($dateDebut)->year;
        $soldeTotal = match ($type) {
            Conge::TYPE_CONGE_PAYE => 25,
            Conge::TYPE_RTT => 10,
            Conge::TYPE_FORMATION => 5,
            default => 0,
        };

        // Calculer les jours déjà pris
        $joursUtilises = Conge::where('employe_id', $this->employeId)
            ->where('type', $type)
            ->whereYear('date_debut', $annee)
            ->whereIn('etat', [
                Conge::ETAT_APPROUVE,
                Conge::ETAT_VALIDE_MANAGER,
                Conge::ETAT_VALIDE_RH,
            ])
            ->when($this->excludeCongeId, fn($q) => $q->where('id', '!=', $this->excludeCongeId))
            ->sum('nombre_jours');

        // Calculer les jours demandés
        $joursDemandes = Carbon::parse($dateDebut)->diffInDays(Carbon::parse($dateFin)) + 1;

        $soldeRestant = $soldeTotal - $joursUtilises;

        if ($joursDemandes > $soldeRestant) {
            $fail("Solde insuffisant. Vous avez demandé {$joursDemandes} jours mais il ne vous reste que {$soldeRestant} jours de {$this->getTypeLabel($type)} pour l'année {$annee}.");
        }
    }

    private function getTypeLabel(string $type): string
    {
        $labels = [
            Conge::TYPE_CONGE_PAYE => 'congés payés',
            Conge::TYPE_RTT => 'RTT',
            Conge::TYPE_FORMATION => 'formation',
        ];
        return $labels[$type] ?? 'congés';
    }
}

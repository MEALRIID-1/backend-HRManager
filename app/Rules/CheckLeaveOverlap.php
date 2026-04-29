<?php

namespace App\Rules;

use App\Models\Conge;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de validation pour vérifier qu'un congé ne chevauche pas un autre congé.
 */
class CheckLeaveOverlap implements ValidationRule
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
        $dateDebut = request()->input('date_debut');
        $dateFin = request()->input('date_fin');

        if (!$dateDebut || !$dateFin) {
            return;
        }

        $debut = Carbon::parse($dateDebut);
        $fin = Carbon::parse($dateFin);

        $query = Conge::where('employe_id', $this->employeId)
            ->where(function ($q) use ($debut, $fin) {
                // Congé existant commence pendant la nouvelle période
                $q->whereBetween('date_debut', [$debut, $fin])
                  // Congé existant finit pendant la nouvelle période
                  ->orWhereBetween('date_fin', [$debut, $fin])
                  // Congé existant englobe complètement la nouvelle période
                  ->orWhere(function ($sq) use ($debut, $fin) {
                      $sq->where('date_debut', '<=', $debut)
                         ->where('date_fin', '>=', $fin);
                  });
            })
            // Ignorer les congés annulés ou refusés
            ->whereNotIn('etat', [
                Conge::ETAT_REFUSE_MANAGER,
                Conge::ETAT_REFUSE_RH,
                Conge::ETAT_REFUSE_DIRECTEUR,
                Conge::ETAT_ANNULE,
            ]);

        // Exclure le congé en cours de modification
        if ($this->excludeCongeId) {
            $query->where('id', '!=', $this->excludeCongeId);
        }

        if ($query->exists()) {
            $congeExistant = $query->first();
            $fail("Vous avez déjà un congé sur cette période (du {$congeExistant->date_debut->format('d/m/Y')} au {$congeExistant->date_fin->format('d/m/Y')}).");
        }
    }
}

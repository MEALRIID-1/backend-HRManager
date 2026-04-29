<?php

namespace App\Rules;

use App\Models\Contrat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de validation personnalisée pour vérifier qu'un contrat ne chevauche pas
 * un autre contrat existant pour le même employé.
 */
class CheckNoContractOverlap implements ValidationRule
{
    private int $employeId;
    private ?int $excludeContratId;

    /**
     * @param int $employeId ID de l'employé
     * @param int|null $excludeContratId ID du contrat à exclure (pour les mises à jour)
     */
    public function __construct(int $employeId, ?int $excludeContratId = null)
    {
        $this->employeId = $employeId;
        $this->excludeContratId = $excludeContratId;
    }

    /**
     * Valider l'attribut.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Récupérer les dates depuis les autres champs de validation
        // Note: cette règle doit être utilisée après que date_debut et date_fin soient validés
        $dateDebut = request()->input('date_debut');
        $dateFin = request()->input('date_fin');

        if (!$dateDebut || !$dateFin) {
            return; // Laisser les autres règles gérer les dates manquantes
        }

        $query = Contrat::where('employe_id', $this->employeId)
            ->where(function ($q) use ($dateDebut, $dateFin) {
                // Chevauchement: un contrat existe sur cette période
                $q->where(function ($sq) use ($dateDebut, $dateFin) {
                    // Contrat existant commence pendant la nouvelle période
                    $sq->whereBetween('date_debut', [$dateDebut, $dateFin]);
                })
                ->orWhere(function ($sq) use ($dateDebut, $dateFin) {
                    // Contrat existant finit pendant la nouvelle période
                    $sq->whereBetween('date_fin', [$dateDebut, $dateFin]);
                })
                ->orWhere(function ($sq) use ($dateDebut, $dateFin) {
                    // Contrat existant englobe complètement la nouvelle période
                    $sq->where('date_debut', '<=', $dateDebut)
                        ->where('date_fin', '>=', $dateFin);
                })
                // CDI sans date de fin englobe tout
                ->orWhere(function ($sq) use ($dateDebut) {
                    $sq->whereNull('date_fin')
                        ->where('type', 'cdi')
                        ->where('date_debut', '<=', $dateDebut);
                });
            })
            ->whereNotIn('etat', ['termine', 'annule']);

        // Exclure le contrat en cours de modification
        if ($this->excludeContratId) {
            $query->where('id', '!=', $this->excludeContratId);
        }

        if ($query->exists()) {
            $contratExistant = $query->first();
            $dateDebutStr = $contratExistant->date_debut->format('d/m/Y');
            $dateFinStr = $contratExistant->date_fin ? $contratExistant->date_fin->format('d/m/Y') : 'illimité';
            $fail("Un contrat existe déjà pour cette période (du {$dateDebutStr} au {$dateFinStr}).");
        }
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle FichePaie.
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "periode": "Mars 2024",
 *   "mois": 3,
 *   "annee": 2024,
 *   "date_paie": "2024-03-31T00:00:00+00:00",
 *   "salaire_brut": 4166.67,
 *   "salaire_net": 3200.00,
 *   "taux_imposition": 23.2,
 *   "deductions": {...},
 *   "employe": {...},
 *   "est_disponible": true,
 *   "created_at": "2024-03-31T12:00:00+00:00",
 *   "_links": {"self": "/api/payslips/1", "download": "/api/payslips/1/download"}
 * }
 */
class FichePaieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasRole(['admin', 'rh']);
        $isEmploye = $request->user()?->id === $this->employe_id;

        return [
            'id' => $this->id,
            
            // Période
            'periode' => $this->periode,
            'mois' => $this->mois,
            'annee' => $this->annee,
            'date_paie' => $this->date_paie?->toIso8601String(),
            
            // Salaires (visible employé ou RH/Admin)
            'salaire_brut' => $this->when($isAdmin || $isEmploye, fn() => round($this->salaire_brut, 2)),
            'salaire_net' => $this->when($isAdmin || $isEmploye, fn() => round($this->salaire_net, 2)),
            
            // Calculs
            'taux_imposition' => $this->when($isAdmin || $isEmploye, fn() => 
                $this->salaire_brut > 0 ? round((($this->salaire_brut - $this->salaire_net) / $this->salaire_brut) * 100, 2) : 0
            ),
            
            // Détail des déductions (admin uniquement)
            'deductions' => $this->when($isAdmin, fn() => [
                'retraite' => round($this->cotisation_retraite ?? 0, 2),
                'securite_sociale' => round($this->cotisation_securite_sociale ?? 0, 2),
                'chomage' => round($this->cotisation_chomage ?? 0, 2),
                'csg_crd' => round($this->csg_crd ?? 0, 2),
                'autres' => round($this->autres_deductions ?? 0, 2),
            ]),
            
            // Heures
            'heures_travaillees' => $this->when($isAdmin || $isEmploye, $this->heures_travaillees),
            'jours_travailles' => $this->when($isAdmin || $isEmploye, $this->jours_travailles),
            
            // Statut
            'est_disponible' => $this->date_paie <= now(),
            'est_future' => $this->date_paie > now(),
            
            // Relations
            'employe' => $this->whenLoaded('employe', fn() => [
                'id' => $this->employe->id,
                'name' => $this->employe->name,
                'email' => $this->employe->email,
            ]),
            
            // Dates
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/payslips/{$this->id}",
                'employe' => "/api/employees/{$this->employe_id}",
                'download' => $this->when($isEmploye || $isAdmin, "/api/payslips/{$this->id}/download"),
            ],
            
            '_meta' => [
                'can_view' => $isEmploye || $isAdmin,
                'can_download' => $isEmploye || $isAdmin,
                'resource_type' => 'payslip',
            ],
        ];
    }
}

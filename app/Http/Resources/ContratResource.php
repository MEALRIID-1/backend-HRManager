<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Contrat.
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "type": "cdi",
 *   "type_label": "CDI",
 *   "etat": "en_cours",
 *   "etat_label": "En cours",
 *   "date_debut": "2023-01-01T00:00:00+00:00",
 *   "date_fin": null,
 *   "salaire": 3500.00,
 *   "salaire_mensuel": 3500.00,
 *   "duree_totale_jours": 450,
 *   "duree_restante_jours": null,
 *   "est_en_periode_essai": false,
 *   "est_expire_sous_30j": false,
 *   "fonction": "Développeur",
 *   "periode_essai": {
 *     "duree_jours": 60,
 *     "progression": 100,
 *     "terminee": true
 *   },
 *   "employe": {...},
 *   "avenants": [...],
 *   "created_at": "2023-01-01T08:30:00+00:00",
 *   "_links": {"self": "/api/contracts/1"}
 * }
 */
class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasRole(['admin', 'rh']);
        $isEmploye = $request->user()?->id === $this->employe_id;

        return [
            'id' => $this->id,
            
            // Type et état avec labels traduits
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'etat' => $this->etat,
            'etat_label' => $this->getEtatLabel(),
            
            // Dates
            'date_debut' => $this->date_debut?->toIso8601String(),
            'date_fin' => $this->date_fin?->toIso8601String(),
            'date_terminaison' => $this->when($isAdmin, fn() => $this->date_terminaison?->toIso8601String()),
            
            // Durées calculées
            'duree_totale_jours' => $this->when($this->duree_totale_jours, fn() => (int) $this->duree_totale_jours),
            'duree_restante_jours' => $this->when($this->duree_restante_jours, fn() => (int) $this->duree_restante_jours),
            
            // Période d'essai
            'est_en_periode_essai' => $this->est_en_periode_essai,
            'periode_essai' => [
                'duree_jours' => $this->duree_periode_essai_jours,
                'progression' => $this->when($this->progression_periode_essai, fn() => round($this->progression_periode_essai, 2)),
                'terminee' => !$this->est_en_periode_essai && $this->duree_periode_essai_jours > 0,
            ],
            
            // Alertes
            'est_expire_sous_30j' => $this->when($this->est_expire_sous_30_jours, true, false),
            'est_expired' => $this->when($this->est_expired, true, false),
            
            // Salaire (visible admin ou employé lui-même)
            'salaire' => $this->when($isAdmin || $isEmploye, fn() => round($this->salaire, 2)),
            'salaire_mensuel' => $this->when($isAdmin || $isEmploye, fn() => round($this->salaire, 2)),
            
            // Poste
            'fonction' => $this->fonction,
            
            // Motif de terminaison (admin uniquement)
            'motif_terminaison' => $this->when($isAdmin, $this->motif_terminaison),
            
            // Relations
            'employe' => $this->whenLoaded('employe', fn() => [
                'id' => $this->employe->id,
                'name' => $this->employe->name,
                'email' => $this->employe->email,
                'photo_url' => $this->employe->photo ? asset('storage/' . $this->employe->photo) : null,
            ]),
            
            'avenants' => $this->whenLoaded('avenants', fn() => 
                $this->avenants->map(fn($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'type_label' => $a->type_formate,
                    'date_effet' => $a->date_effet?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ])
            ),
            
            'createur' => $this->whenLoaded('createur', fn() => [
                'id' => $this->createur->id,
                'name' => $this->createur->name,
            ]),
            
            // Dates système
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/contracts/{$this->id}",
                'employe' => "/api/employees/{$this->employe_id}",
                'avenants' => "/api/contracts/{$this->id}/avenants",
            ],
            
            // Métadonnées
            '_meta' => [
                'can_terminate' => $isAdmin && $this->etat === 'en_cours',
                'can_update' => $isAdmin,
                'resource_type' => 'contract',
            ],
        ];
    }

    private function getTypeLabel(): string
    {
        $labels = [
            'cdi' => 'CDI',
            'cdd' => 'CDD',
            'stage' => 'Stage',
            'alternance' => 'Alternance',
        ];
        return $labels[$this->type] ?? strtoupper($this->type);
    }

    private function getEtatLabel(): string
    {
        $labels = [
            'en_cours' => 'En cours',
            'periode_essai' => 'Période d\'essai',
            'suspendu' => 'Suspendu',
            'termine' => 'Terminé',
        ];
        return $labels[$this->etat] ?? ucfirst($this->etat);
    }
}

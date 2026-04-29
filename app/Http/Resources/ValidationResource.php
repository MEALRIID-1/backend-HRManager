<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Validation.
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "conge_id": 5,
 *   "niveau": 1,
 *   "niveau_label": "Manager",
 *   "action": "approuve",
 *   "action_label": "Approuvé",
 *   "motif": null,
 *   "validateur": {"id": 3, "name": "Jane Doe"},
 *   "date_validation": "2024-02-20T14:30:00+00:00",
 *   "conge": {...},
 *   "created_at": "2024-02-20T14:30:00+00:00",
 *   "_links": {"self": "/api/validations/1"}
 * }
 */
class ValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // IDs de référence
            'conge_id' => $this->conge_id,
            'validateur_id' => $this->validateur_id,
            
            // Niveau avec label
            'niveau' => $this->niveau,
            'niveau_label' => $this->niveau_label ?? $this->getNiveauLabel(),
            
            // Action avec label
            'action' => $this->action,
            'action_label' => $this->action_label ?? $this->getActionLabel(),
            
            // Motif (si refus)
            'motif' => $this->motif,
            
            // Date de validation
            'date_validation' => $this->date_validation?->toIso8601String(),
            
            // Relations
            'validateur' => $this->whenLoaded('validateur', fn() => [
                'id' => $this->validateur->id,
                'name' => $this->validateur->name,
                'email' => $this->validateur->email,
                'roles' => $this->validateur->roles->pluck('name'),
            ]),
            
            'conge' => $this->whenLoaded('conge', fn() => [
                'id' => $this->conge->id,
                'type' => $this->conge->type,
                'type_label' => $this->conge->type_label ?? null,
                'etat' => $this->conge->etat,
                'date_debut' => $this->conge->date_debut?->toIso8601String(),
                'date_fin' => $this->conge->date_fin?->toIso8601String(),
                'nombre_jours' => $this->conge->nombre_jours,
                'employe' => [
                    'id' => $this->conge->employe?->id,
                    'name' => $this->conge->employe?->name,
                ],
            ]),
            
            // Dates système
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/validations/{$this->id}",
                'conge' => "/api/leaves/{$this->conge_id}",
                'validateur' => "/api/users/{$this->validateur_id}",
            ],
            
            '_meta' => [
                'resource_type' => 'validation',
                'est_approbation' => $this->action === 'approuve',
                'est_refus' => $this->action === 'refuse',
            ],
        ];
    }

    private function getNiveauLabel(): string
    {
        $labels = [
            1 => 'Manager',
            2 => 'RH',
            3 => 'Directeur',
        ];
        return $labels[$this->niveau] ?? 'Inconnu';
    }

    private function getActionLabel(): string
    {
        $labels = [
            'approuve' => 'Approuvé',
            'refuse' => 'Refusé',
        ];
        return $labels[$this->action] ?? ucfirst($this->action);
    }
}

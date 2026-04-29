<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Conge.
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "type": "conge_paye",
 *   "type_label": "Congé payé",
 *   "etat": "approuve",
 *   "etat_label": "Approuvé",
 *   "date_debut": "2024-03-01T00:00:00+00:00",
 *   "date_fin": "2024-03-05T00:00:00+00:00",
 *   "nombre_jours": 5,
 *   "raison": "Vacances",
 *   "commentaire": "...",
 *   "peut_etre_annule": false,
 *   "est_approuve": true,
 *   "est_refuse": false,
 *   "employe": {...},
 *   "validations": [...],
 *   "created_at": "2024-02-15T10:30:00+00:00",
 *   "_links": {"self": "/api/leaves/1"}
 * }
 */
class CongeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasRole(['admin', 'rh']);
        $isEmploye = $request->user()?->id === $this->employe_id;
        $isManager = $request->user()?->id === ($this->employe?->manager_id ?? null);

        return [
            'id' => $this->id,
            
            // Type avec label
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            
            // État avec label
            'etat' => $this->etat,
            'etat_label' => $this->etat_label ?? $this->getEtatLabel(),
            'workflow_etape_suivante' => $this->getProchaineEtape(),
            
            // Dates du congé
            'date_debut' => $this->date_debut?->toIso8601String(),
            'date_fin' => $this->date_fin?->toIso8601String(),
            'nombre_jours' => (int) $this->nombre_jours,
            
            // Contenu
            'raison' => $this->raison,
            'commentaire' => $this->commentaire,
            
            // Flags calculés
            'peut_etre_annule' => $this->peutEtreAnnule(),
            'peut_etre_approuve' => $this->enAttenteValidation(),
            'est_approuve' => $this->estApprouve(),
            'est_refuse' => $this->estRefuse(),
            'est_annule' => $this->etat === 'annule',
            'est_passe' => $this->date_fin < now(),
            'est_en_cours' => $this->date_debut <= now() && $this->date_fin >= now(),
            'est_futur' => $this->date_debut > now(),
            
            // Motif d'annulation (visible créateur ou admin)
            'motif_annulation' => $this->when($isAdmin || ($isEmploye && $this->etat === 'annule'), $this->motif_annulation),
            'annule_par' => $this->whenLoaded('annulePar', fn() => [
                'id' => $this->annulePar?->id,
                'name' => $this->annulePar?->name,
            ]),
            'annule_le' => $this->when($this->annule_le, fn() => $this->annule_le?->toIso8601String()),
            
            // Relations
            'employe' => $this->whenLoaded('employe', fn() => [
                'id' => $this->employe->id,
                'name' => $this->employe->name,
                'email' => $this->employe->email,
                'photo_url' => $this->employe->photo ? asset('storage/' . $this->employe->photo) : null,
                'manager_id' => $this->employe->manager_id,
            ]),
            
            'validations' => $this->whenLoaded('validations', fn() => 
                $this->validations->map(fn($v) => [
                    'id' => $v->id,
                    'niveau' => $v->niveau,
                    'niveau_label' => $v->niveau_label,
                    'action' => $v->action,
                    'action_label' => $v->action_label,
                    'motif' => $v->motif,
                    'validateur' => [
                        'id' => $v->validateur?->id,
                        'name' => $v->validateur?->name,
                    ],
                    'date_validation' => $v->date_validation?->toIso8601String(),
                ])
            ),
            
            'validateurs' => $this->whenLoaded('validateurs', fn() => 
                $this->validateurs->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])
            ),
            
            // Validation individuelle
            'validation_manager' => $this->whenLoaded('validationManager', fn() => $this->validationManager ? [
                'id' => $this->validationManager->id,
                'action' => $this->validationManager->action,
                'validateur' => $this->validationManager->validateur?->name,
                'date' => $this->validationManager->date_validation?->toIso8601String(),
            ] : null),
            
            'validation_rh' => $this->whenLoaded('validationRH', fn() => $this->validationRH ? [
                'id' => $this->validationRH->id,
                'action' => $this->validationRH->action,
                'validateur' => $this->validationRH->validateur?->name,
                'date' => $this->validationRH->date_validation?->toIso8601String(),
            ] : null),
            
            'validation_directeur' => $this->whenLoaded('validationDirecteur', fn() => $this->validationDirecteur ? [
                'id' => $this->validationDirecteur->id,
                'action' => $this->validationDirecteur->action,
                'validateur' => $this->validationDirecteur->validateur?->name,
                'date' => $this->validationDirecteur->date_validation?->toIso8601String(),
            ] : null),
            
            // Dates système
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/leaves/{$this->id}",
                'employe' => "/api/employees/{$this->employe_id}",
                'submit' => $this->when($this->etat === 'brouillon', "/api/leaves/{$this->id}/submit"),
                'approve' => $this->when($this->enAttenteValidation(), "/api/leaves/{$this->id}/approve"),
                'reject' => $this->when($this->enAttenteValidation(), "/api/leaves/{$this->id}/reject"),
                'cancel' => $this->when($this->peutEtreAnnule(), "/api/leaves/{$this->id}/cancel"),
            ],
            
            // Métadonnées
            '_meta' => [
                'can_submit' => $isEmploye && $this->etat === 'brouillon',
                'can_approve' => ($isManager || $isAdmin) && $this->enAttenteValidation(),
                'can_cancel' => $isEmploye && $this->peutEtreAnnule(),
                'resource_type' => 'leave',
            ],
        ];
    }

    private function getTypeLabel(): string
    {
        $labels = [
            'conge_paye' => 'Congé payé',
            'rtt' => 'RTT',
            'conge_sans_solde' => 'Congé sans solde',
            'maladie' => 'Maladie',
            'formation' => 'Formation',
            'maternite' => 'Maternité',
            'paternite' => 'Paternité',
        ];
        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    private function getEtatLabel(): string
    {
        $labels = [
            'brouillon' => 'Brouillon',
            'soumis' => 'Soumis',
            'valide_manager' => 'Validé par le manager',
            'valide_rh' => 'Validé par RH',
            'approuve' => 'Approuvé',
            'refuse_manager' => 'Refusé par le manager',
            'refuse_rh' => 'Refusé par RH',
            'refuse_directeur' => 'Refusé par le directeur',
            'annule' => 'Annulé',
        ];
        return $labels[$this->etat] ?? ucfirst($this->etat);
    }

    private function getProchaineEtape(): ?string
    {
        return match ($this->etat) {
            'brouillon' => 'soumettre',
            'soumis' => 'validation_manager',
            'valide_manager' => 'validation_rh',
            'valide_rh' => 'approbation_directeur',
            default => null,
        };
    }
}

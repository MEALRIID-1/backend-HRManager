<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle User (Employé).
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "name": "John Doe",
 *   "email": "john@example.com",
 *   "telephone": "+33123456789",
 *   "photo_url": "http://.../photos/john.jpg",
 *   "date_embauche": "2020-01-15T00:00:00+00:00",
 *   "anciennete_annees": 4.2,
 *   "est_actif": true,
 *   "created_at": "2020-01-15T08:30:00+00:00",
 *   "updated_at": "2024-01-15T10:20:00+00:00",
 *   "roles": ["employe", "manager"],
 *   "permissions": ["view-leaves", "approve-leaves"],
 *   "departement": {...},
 *   "manager": {...},
 *   "contrat_actif": {...},
 *   "solde_conges": {"conge_paye": 15, "rtt": 5},
 *   "_links": {
 *     "self": "/api/users/1",
 *     "contrats": "/api/users/1/contracts",
 *     "conges": "/api/users/1/leaves"
 *   }
 * }
 */
class UserResource extends JsonResource
{
    /**
     * Transforme la ressource en tableau.
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasRole(['admin', 'rh']);
        $isSelf = $request->user()?->id === $this->id;
        $isManager = $request->user()?->id === $this->manager_id;

        return [
            // Champs de base
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'adresse' => $this->when($isAdmin || $isSelf, $this->adresse),
            
            // Photo avec URL complète
            'photo_url' => $this->when($this->photo, asset('storage/' . $this->photo)),
            
            // Dates employé
            'date_embauche' => $this->when($this->date_embauche, fn() => $this->date_embauche?->toIso8601String()),
            'date_depart' => $this->when($this->date_depart, fn() => $this->date_depart?->toIso8601String()),
            
            // Champs calculés
            'anciennete_annees' => $this->when($this->date_embauche, fn() => round($this->date_embauche?->diffInYears(now()) ?? 0, 1)),
            'est_actif' => $this->est_actif,
            
            // Dates système
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'email_verified_at' => $this->when($isSelf, fn() => $this->email_verified_at?->toIso8601String()),
            
            // Relations
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded('permissions', fn() => $this->permissions->pluck('name')),
            
            'departement' => $this->whenLoaded('departement', fn() => [
                'id' => $this->departement->id,
                'nom' => $this->departement->nom,
            ]),
            
            'manager' => $this->whenLoaded('manager', fn() => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ]),
            
            'equipe' => $this->whenLoaded('equipe', fn() => $this->equipe->map(fn($membre) => [
                'id' => $membre->id,
                'name' => $membre->name,
                'email' => $membre->email,
                'photo_url' => $membre->photo ? asset('storage/' . $membre->photo) : null,
            ])),
            
            // Données RH sensibles (admin/RH uniquement)
            'salaire_actuel' => $this->when($isAdmin, fn() => $this->whenLoaded('contrats', function () {
                $contrat = $this->contrats->firstWhere('etat', 'en_cours');
                return $contrat ? round($contrat->salaire, 2) : null;
            })),
            
            'contrat_actif' => $this->whenLoaded('contrats', function () {
                $contrat = $this->contrats->firstWhere('etat', 'en_cours');
                return $contrat ? [
                    'id' => $contrat->id,
                    'type' => $contrat->type,
                    'date_debut' => $contrat->date_debut->toIso8601String(),
                    'date_fin' => $contrat->date_fin?->toIso8601String(),
                    'est_en_periode_essai' => $contrat->est_en_periode_essai,
                ] : null;
            }),
            
            // Solde de congés (visible par soi-même ou RH)
            'solde_conges' => $this->when($isSelf || $isAdmin || $isManager, function () {
                return [
                    'conge_paye' => [
                        'total_annuel' => 25,
                        'jours_utilises' => (int) ($this->conges_annee ?? 0),
                        'jours_restants' => max(0, 25 - (int) ($this->conges_annee ?? 0)),
                    ],
                    'rtt' => [
                        'total_annuel' => 10,
                        'jours_utilises' => 0,
                        'jours_restants' => 10,
                    ],
                ];
            }),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/users/{$this->id}",
                'contrats' => "/api/users/{$this->id}/contracts",
                'conges' => "/api/users/{$this->id}/leaves",
                'paies' => $this->when($isSelf, "/api/users/{$this->id}/payslips"),
            ],
            
            // Métadonnées
            '_meta' => [
                'can_edit' => $isAdmin || $isSelf,
                'can_delete' => $isAdmin && $this->id !== $request->user()?->id,
                'resource_type' => 'user',
            ],
        ];
    }
}

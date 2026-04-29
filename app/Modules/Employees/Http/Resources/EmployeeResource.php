<?php

namespace App\Modules\Employees\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour un employé
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transformer la ressource en tableau.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_complet' => $this->nom_complet,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'adresse' => $this->adresse,
            'photo_url' => $this->photo_url,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'anciennete' => $this->anciennete,
            'anciennete_formatee' => $this->anciennete_formatee,
            'est_actif' => $this->est_actif,
            'departement_id' => $this->departement_id,
            'manager_id' => $this->manager_id,

            // Relations
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),

            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'nom' => $this->manager->name,
                ];
            }),

            // Contrat actif
            'contrat_actif' => $this->whenLoaded('contrats', function () {
                $contratActif = $this->contratActif();
                return $contratActif ? [
                    'id' => $contratActif->id,
                    'type' => $contratActif->type,
                    'date_debut' => $contratActif->date_debut?->format('Y-m-d'),
                    'date_fin' => $contratActif->date_fin?->format('Y-m-d'),
                    'salaire' => $contratActif->salaire,
                ] : null;
            }),

            // Solde de congés
            'solde_conges' => $this->solde_conges,

            // Dernière fiche de paie
            'derniere_fiche_paie' => $this->whenLoaded('fichePaies', function () {
                $fiche = $this->derniereFichePaie();
                return $fiche ? [
                    'id' => $fiche->id,
                    'periode' => $fiche->periode,
                    'montant' => $fiche->montant,
                    'etat' => $fiche->etat,
                ] : null;
            }),

            // Compteurs
            'nombre_conges' => $this->whenCounted('conges'),
            'nombre_fiches_paie' => $this->whenCounted('fichePaies'),

            // Dates
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

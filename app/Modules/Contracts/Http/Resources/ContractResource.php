<?php

namespace App\Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'salaire' => $this->salaire,
            'etat' => $this->etat,
            'statut_formate' => $this->statut_formate,

            // Période d'essai
            'est_en_periode_essai' => $this->est_en_periode_essai,
            'duree_periode_essai_jours' => $this->duree_periode_essai_jours,
            'progression_periode_essai' => $this->progression_periode_essai,

            // Durées
            'duree_restante' => $this->duree_restante,
            'duree_totale_jours' => $this->duree_totale_jours,
            'est_expire_sous_30_jours' => $this->est_expire_sous_30_jours,

            // Terminaison
            'motif_terminaison' => $this->when($this->motif_terminaison, $this->motif_terminaison),
            'date_terminaison' => $this->when($this->date_terminaison, $this->date_terminaison?->format('Y-m-d')),

            // Relations
            'employe' => $this->whenLoaded('employe', function () {
                return [
                    'id' => $this->employe->id,
                    'nom' => $this->employe->name,
                    'email' => $this->employe->email,
                    'photo_url' => $this->employe->photo_url,
                ];
            }),

            // Historique des avenants
            'avenants' => $this->whenLoaded('avenants', function () {
                return $this->avenants->map(function ($avenant) {
                    return [
                        'id' => $avenant->id,
                        'type' => $avenant->type_modification,
                        'type_formate' => $avenant->type_formate,
                        'ancienne_valeur' => $avenant->ancienne_valeur,
                        'nouvelle_valeur' => $avenant->nouvelle_valeur,
                        'motif' => $avenant->motif,
                        'date_effet' => $avenant->date_effet?->format('Y-m-d'),
                        'created_at' => $avenant->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            // Dates système
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at, fn() => $this->deleted_at?->toIso8601String()),
            
            // Informations d'archivage (corbeille)
            'etat_avant_archivage' => $this->when($this->etat_avant_archivage, $this->etat_avant_archivage),
            'is_archived' => !is_null($this->deleted_at),
            'date_archivage' => $this->when($this->deleted_at, fn() => $this->deleted_at?->format('d/m/Y H:i')),
        ];
    }
}

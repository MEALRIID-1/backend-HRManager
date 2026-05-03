<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'statut' => $this->statut,
            'etat' => $this->statut,
            'salaire_brut' => $this->salaire_brut,
            'salaire_base' => $this->salaire_brut,
            'poste' => $this->poste,
            'departement' => $this->departement,
            'pdf_path' => $this->pdf_path,
            'pdf_url' => $this->pdf_path ? asset('storage/' . $this->pdf_path) : null,
            'employe' => $this->whenLoaded('employe', function () {
                return [
                    'id' => $this->employe->id,
                    'nom' => $this->employe->nom,
                    'prenom' => $this->employe->prenom,
                    'email' => $this->employe->email,
                    'departement' => $this->employe->departement,
                    'date_embauche' => $this->employe->date_embauche?->format('Y-m-d'),
                    'iban' => $this->employe->iban,
                ];
            }),
            'est_actif' => $this->statut === 'actif' && ($this->date_fin === null || $this->date_fin >= now()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}

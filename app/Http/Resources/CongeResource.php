<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Conge
 */
class CongeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypes()[$this->type] ?? $this->type,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'nombre_jours' => $this->nombre_jours,
            'statut' => $this->statut,
            'statut_label' => $this->getStatuts()[$this->statut] ?? $this->statut,
            'motif' => $this->motif_refus ?? $this->motif,
            'motif_refus' => $this->motif_refus,
            'commentaire' => $this->commentaire,
            'employe' => $this->whenLoaded('employe', fn () => [
                'id' => $this->employe->id,
                'nom' => $this->employe->nom,
                'prenom' => $this->employe->prenom,
                'departement' => $this->employe->departement,
            ]),
            'remplacant' => $this->whenLoaded('remplacant', fn () => [
                'id' => $this->remplacant->id,
                'name' => $this->remplacant->name,
            ]),
            'validations' => ValidationResource::collection($this->whenLoaded('validations')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
    
    // ✅ Ajouter ces méthodes si elles n'existent pas dans le modèle
    protected function getTypes(): array
    {
        return [
            'conge_paye' => 'Congé payé',
            'rtt' => 'RTT',
            'conge_sans_solde' => 'Congé sans solde',
            'maladie' => 'Maladie',
            'formation' => 'Formation',
        ];
    }
    
    protected function getStatuts(): array
    {
        return [
            'en_attente' => 'En attente',
            'approuve' => 'Approuvé',
            'partiellement_valide' => 'Partiellement validé',
            'refuse' => 'Refusé',
        ];
    }
}
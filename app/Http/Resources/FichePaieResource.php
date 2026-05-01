<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FichePaie
 */
class FichePaieResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mois' => $this->mois,
            'annee' => $this->annee,
            'periode' => "{$this->mois} {$this->annee}",
            'salaire_base' => $this->salaire_base,
            'heures_sup' => $this->heures_sup,
            'absences' => $this->absences,
            'net_a_payer' => $this->net_a_payer,
            'statut' => $this->statut,
            'statut_label' => $this->getStatutLabel(),
            'pdf_url' => $this->pdf_url ? asset('storage/' . $this->pdf_url) : null,
            'employe' => $this->whenLoaded('employe', fn () => [
                'id' => $this->employe->id,
                'nom' => $this->employe->nom,
                'prenom' => $this->employe->prenom,
                'matricule' => $this->employe->matricule,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get statut label.
     */
    private function getStatutLabel(): string
    {
        $labels = [
            'brouillon' => 'Brouillon',
            'finalisee' => 'Finalisée',
            'payee' => 'Payée',
        ];

        return $labels[$this->statut] ?? $this->statut;
    }
}

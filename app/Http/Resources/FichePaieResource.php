<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

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
        // Extraire mois et année depuis 'periode'
        try {
            $date = Carbon::parse($this->periode);
            $mois = $date->month;
            $annee = $date->year;
        } catch (\Exception $e) {
            $mois = 1;
            $annee = now()->year;
        }
        
        return [
            'id' => $this->id,
            'mois' => $mois,
            'annee' => $annee,
            'periode' => $this->periode,
            'salaire_base' => $this->salaire_brut,
            'heures_sup' => $this->heures_supplementaires ?? 0,
            'absences' => $this->absences ?? 0,
            'net_a_payer' => $this->salaire_net,
            'statut' => $this->statut,
            'statut_label' => $this->getStatutLabel(),
            'pdf_url' => $this->document_path ? asset('storage/' . $this->document_path) : null,
            'employe' => $this->whenLoaded('employe', function () {
                return [
                    'id' => $this->employe->id,
                    'nom' => $this->employe->nom,
                    'prenom' => $this->employe->prenom,
                    'matricule' => $this->employe->matricule ?? '',
                    'email' => $this->employe->email,
                ];
            }),
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
            'validee' => 'Validée',        // ← Ajoute selon tes valeurs
            'payee' => 'Payée',
            'finalisee' => 'Finalisée',
        ];

        return $labels[$this->statut] ?? $this->statut;
    }
}
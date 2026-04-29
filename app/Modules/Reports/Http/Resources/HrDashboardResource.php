<?php

namespace App\Modules\Reports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le Dashboard RH
 * Structure optimisée pour les graphiques et KPIs
 */
class HrDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kpis' => [
                'effectif' => [
                    'total_employes' => $this['total_employes'],
                    'nouveaux_30j' => $this['nouveaux_employes_30j'],
                    'departs_30j' => $this['departs_30j'],
                    'evolution' => $this['evolution_effectif'],
                ],
                'conges' => $this['conges_en_attente'],
                'contrats' => [
                    'expirant_30j' => $this['contrats_expirant_30j'],
                ],
                'financier' => [
                    'masse_salariale_mois' => $this['masse_salariale_mois'],
                ],
                'absenteisme' => [
                    'taux_mensuel' => $this['taux_absenteisme_mois'],
                ],
            ],
            'alertes' => $this['contrats_expirant_details'] ?? [],
            'date_generation' => now()->format('Y-m-d H:i:s'),
        ];
    }
}

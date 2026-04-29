<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Conges.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "summary": {
 *     "total": 100,
 *     "par_etat": {"brouillon": 10, "soumis": 20, "approuve": 50, ...},
 *     "par_type": {"conge_paye": 60, "rtt": 25, ...},
 *     "jours_total": 450
 *   },
 *   "meta": {...}
 * }
 */
class CongeCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => CongeResource::collection($this->collection),
            
            'meta' => $this->when($this->resource instanceof \Illuminate\Pagination\AbstractPaginator, function () {
                return [
                    'total' => $this->total(),
                    'per_page' => $this->perPage(),
                    'current_page' => $this->currentPage(),
                    'last_page' => $this->lastPage(),
                    'from' => $this->firstItem(),
                    'to' => $this->lastItem(),
                ];
            }),
            
            'summary' => [
                'total' => $this->collection->count(),
                'par_etat' => $this->collection->countBy('etat'),
                'par_type' => $this->collection->countBy('type'),
                'jours_total' => (int) $this->collection->sum('nombre_jours'),
                'en_attente' => $this->collection->whereIn('etat', ['soumis', 'valide_manager', 'valide_rh'])->count(),
                'approuves' => $this->collection->where('etat', 'approuve')->count(),
                'refuses' => $this->collection->whereIn('etat', ['refuse_manager', 'refuse_rh', 'refuse_directeur'])->count(),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Fiches de Paie.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "summary": {
 *     "total": 24,
 *     "masse_salariale_annuelle": 45000.00,
 *     "moyenne_mensuelle": 3750.00,
 *     "par_annee": {"2024": 12000.00, "2023": 33000.00}
 *   },
 *   "meta": {...}
 * }
 */
class FichePaieCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasRole(['admin', 'rh']);

        return [
            'data' => FichePaieResource::collection($this->collection),
            
            'meta' => $this->when($this->resource instanceof \Illuminate\Pagination\AbstractPaginator, function () {
                return [
                    'total' => $this->total(),
                    'per_page' => $this->perPage(),
                    'current_page' => $this->currentPage(),
                    'last_page' => $this->lastPage(),
                ];
            }),
            
            'summary' => [
                'total' => $this->collection->count(),
                'par_annee' => $this->collection->groupBy('annee')
                    ->map(fn($items) => [
                        'count' => $items->count(),
                        'total_net' => $isAdmin ? round($items->sum('salaire_net'), 2) : null,
                    ]),
                'disponibles' => $this->collection->where('date_paie', '<=', now())->count(),
                'futures' => $this->collection->where('date_paie', '>', now())->count(),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Contrats.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "summary": {
 *     "total": 50,
 *     "par_type": {"cdi": 30, "cdd": 15, "stage": 5},
 *     "par_etat": {"en_cours": 45, "termine": 5},
 *     "expirant_30j": 3
 *   },
 *   "meta": {...}
 * }
 */
class ContratCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ContratResource::collection($this->collection),
            
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
                'par_type' => $this->collection->countBy('type'),
                'par_etat' => $this->collection->countBy('etat'),
                'en_cours' => $this->collection->where('etat', 'en_cours')->count(),
                'expirant_30j' => $this->collection->filter(fn($c) => 
                    $c->est_expire_sous_30_jours ?? false
                )->count(),
            ],
        ];
    }
}

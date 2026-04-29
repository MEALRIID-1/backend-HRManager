<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Validations.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "summary": {
 *     "total": 50,
 *     "par_niveau": {"1": 20, "2": 15, "3": 15},
 *     "par_action": {"approuve": 40, "refuse": 10}
 *   },
 *   "meta": {...}
 * }
 */
class ValidationCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => ValidationResource::collection($this->collection),
            
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
                'par_niveau' => $this->collection->countBy('niveau'),
                'par_action' => $this->collection->countBy('action'),
                'approbations' => $this->collection->where('action', 'approuve')->count(),
                'refus' => $this->collection->where('action', 'refuse')->count(),
            ],
        ];
    }
}

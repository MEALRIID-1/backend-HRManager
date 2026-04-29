<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Users.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "meta": {
 *     "total": 150,
 *     "per_page": 15,
 *     "current_page": 1,
 *     "last_page": 10,
 *     "from": 1,
 *     "to": 15,
 *     "links": {...}
 *   },
 *   "summary": {
 *     "actifs": 120,
 *     "inactifs": 30
 *   }
 * }
 */
class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => UserResource::collection($this->collection),
            
            'meta' => $this->when($this->resource instanceof \Illuminate\Pagination\AbstractPaginator, function () {
                return [
                    'total' => $this->total(),
                    'per_page' => $this->perPage(),
                    'current_page' => $this->currentPage(),
                    'last_page' => $this->lastPage(),
                    'from' => $this->firstItem(),
                    'to' => $this->lastItem(),
                    'links' => [
                        'first' => $this->url(1),
                        'last' => $this->url($this->lastPage()),
                        'prev' => $this->previousPageUrl(),
                        'next' => $this->nextPageUrl(),
                        'self' => $this->url($this->currentPage()),
                    ],
                ];
            }),
            
            // Résumé pour les listes
            'summary' => [
                'total_count' => $this->collection->count(),
                'actifs' => $this->collection->where('est_actif', true)->count(),
                'inactifs' => $this->collection->where('est_actif', false)->count(),
            ],
        ];
    }
}

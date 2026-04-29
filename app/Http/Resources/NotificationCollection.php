<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Notifications.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "summary": {
 *     "total": 50,
 *     "non_lues": 5,
 *     "par_type": {"conge_approved": 10, "payslip_available": 5, ...}
 *   },
 *   "meta": {...}
 * }
 */
class NotificationCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => NotificationResource::collection($this->collection),
            
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
                'non_lues' => $this->collection->whereNull('read_at')->count(),
                'lues' => $this->collection->whereNotNull('read_at')->count(),
                'par_type' => $this->collection->countBy('type'),
                'par_priorite' => $this->collection->countBy('priority'),
                'haute_priorite' => $this->collection->where('priority', 'high')->count(),
            ],
        ];
    }
}

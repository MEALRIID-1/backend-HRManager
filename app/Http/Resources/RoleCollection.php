<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Roles.
 */
class RoleCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => RoleResource::collection($this->collection),
            
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
                'total_roles' => $this->collection->count(),
            ],
        ];
    }
}

<?php

namespace App\Modules\Leaves\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LeaveCollection extends ResourceCollection
{
    public $collects = LeaveResource::class;

    public function toArray($request): array
    {
        $isPaginated = method_exists($this->resource, 'total');

        return [
            'data' => $this->collection,
            'meta' => $isPaginated ? [
                'total'        => $this->resource->total(),
                'per_page'     => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page'    => $this->resource->lastPage(),
            ] : [
                'total' => $this->collection->count(),
            ],
        ];
    }
}

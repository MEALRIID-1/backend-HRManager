<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginatedResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->resource;

        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'self' => $paginator->url($paginator->currentPage()),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return [
            'success' => true,
            'message' => 'Données récupérées avec succès',
        ];
    }
}

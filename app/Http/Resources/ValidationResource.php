<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Validation
 */
class ValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypes()[$this->type] ?? $this->type,
            'statut' => $this->statut,
            'statut_label' => $this->getStatuts()[$this->statut] ?? $this->statut,
            'commentaire' => $this->commentaire,
            'date_validation' => $this->date_validation?->format('Y-m-d H:i:s'),
            'niveau' => $this->niveau,
            'validateur' => $this->whenLoaded('validateur', fn () => [
                'id' => $this->validateur->id,
                'name' => $this->validateur->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

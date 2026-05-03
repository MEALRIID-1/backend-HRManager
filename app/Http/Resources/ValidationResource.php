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
            'niveau' => $this->niveau,
            'decision' => $this->decision,
            'commentaire' => $this->commentaire,
            'date_validation' => $this->date_validation?->format('Y-m-d H:i:s'),
            'validateur' => $this->whenLoaded('validateur', fn () => [
                'id' => $this->validateur->id,
                'nom' => $this->validateur->nom,
                'prenom' => $this->validateur->prenom,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
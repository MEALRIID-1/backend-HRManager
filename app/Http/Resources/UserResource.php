<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * IMPORTANT: Sensitive fields like password and remember_token are NEVER exposed.
     * Only safe, non-sensitive data is returned in API responses.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'departement' => $this->departement,
            'photo_profil' => $this->photo_profil ? asset('storage/' . $this->photo_profil) : null,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'iban' => $this->iban,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'nom' => $role->nom,
                    'niveau_hierarchique' => $role->niveau_hierarchique,
                ]);
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}

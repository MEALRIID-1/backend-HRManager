<?php

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    private ?string $token;

    public function __construct($resource, ?string $token = null)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->values()->all()
            : [];

        $permissions = $this->relationLoaded('roles')
            ? $this->roles->flatMap->permissions->pluck('name')->unique()->values()->all()
            : [];

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'role' => $roles[0] ?? null,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'token' => $this->when($this->token, $this->token),
        ];
    }
}
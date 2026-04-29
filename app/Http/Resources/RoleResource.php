<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Role (Spatie Permission).
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "name": "manager",
 *   "guard_name": "web",
 *   "display_name": "Manager",
 *   "description": "Gestionnaire d'équipe",
 *   "permissions_count": 15,
 *   "users_count": 8,
 *   "permissions": [...],
 *   "created_at": "2020-01-15T08:30:00+00:00",
 *   "_links": {"self": "/api/roles/1"}
 * }
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            
            // Métadonnées traduites
            'display_name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            
            // Compteurs
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            
            // Relations
            'permissions' => $this->whenLoaded('permissions', fn() => 
                $this->permissions->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'module' => $p->module ?? 'general',
                ])
            ),
            
            'users' => $this->whenLoaded('users', fn() => 
                $this->users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])
            ),
            
            // Dates
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/roles/{$this->id}",
                'permissions' => "/api/roles/{$this->id}/permissions",
                'users' => "/api/roles/{$this->id}/users",
            ],
        ];
    }

    /**
     * Traduction du nom du rôle.
     */
    private function getDisplayName(): string
    {
        $names = [
            'admin' => 'Administrateur',
            'directeur' => 'Directeur',
            'rh' => 'Ressources Humaines',
            'manager' => 'Manager',
            'employe' => 'Employé',
        ];

        return $names[$this->name] ?? ucfirst($this->name);
    }

    /**
     * Description du rôle.
     */
    private function getDescription(): ?string
    {
        $descriptions = [
            'admin' => 'Accès complet à tous les modules et fonctionnalités',
            'directeur' => 'Validation finale, accès aux rapports et statistiques',
            'rh' => 'Gestion des employés, contrats, paies et congés',
            'manager' => 'Gestion de l\'équipe et validation des congés',
            'employe' => 'Accès à ses propres informations et demandes de congés',
        ];

        return $descriptions[$this->name] ?? null;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Permission (Spatie Permission).
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": 1,
 *   "name": "view-users",
 *   "guard_name": "web",
 *   "module": "users",
 *   "action": "view",
 *   "display_name": "Voir les utilisateurs",
 *   "description": "Permet de consulter la liste des utilisateurs",
 *   "roles_count": 3,
 *   "roles": [...],
 *   "created_at": "2020-01-15T08:30:00+00:00"
 * }
 */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            
            // Parsing du nom pour extraire module et action
            'module' => $this->getModuleFromName(),
            'action' => $this->getActionFromName(),
            
            // Métadonnées
            'display_name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            
            // Compteurs
            'roles_count' => $this->whenCounted('roles'),
            
            // Relations
            'roles' => $this->whenLoaded('roles', fn() => 
                $this->roles->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ])
            ),
            
            // Dates
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/permissions/{$this->id}",
                'roles' => "/api/permissions/{$this->id}/roles",
            ],
        ];
    }

    /**
     * Extrait le module du nom de permission (ex: view-users -> users).
     */
    private function getModuleFromName(): string
    {
        $parts = explode('-', $this->name);
        return end($parts) ?? 'general';
    }

    /**
     * Extrait l'action du nom de permission (ex: view-users -> view).
     */
    private function getActionFromName(): string
    {
        $parts = explode('-', $this->name);
        return $parts[0] ?? 'unknown';
    }

    /**
     * Traduction du nom de la permission.
     */
    private function getDisplayName(): string
    {
        $module = $this->getModuleFromName();
        $action = $this->getActionFromName();
        
        $actions = [
            'view' => 'Voir',
            'create' => 'Créer',
            'edit' => 'Modifier',
            'delete' => 'Supprimer',
            'manage' => 'Gérer',
            'assign' => 'Assigner',
            'revoke' => 'Révoquer',
            'approve' => 'Approuver',
            'reject' => 'Refuser',
        ];
        
        $modules = [
            'users' => 'les utilisateurs',
            'roles' => 'les rôles',
            'permissions' => 'les permissions',
            'contracts' => 'les contrats',
            'leaves' => 'les congés',
            'payslips' => 'les fiches de paie',
            'employees' => 'les employés',
            'reports' => 'les rapports',
        ];
        
        return ($actions[$action] ?? $action) . ' ' . ($modules[$module] ?? $module);
    }

    /**
     * Description de la permission.
     */
    private function getDescription(): ?string
    {
        $descriptions = [
            'view-users' => 'Consulter la liste et les détails des utilisateurs',
            'create-users' => 'Créer de nouveaux utilisateurs',
            'edit-users' => 'Modifier les informations des utilisateurs',
            'delete-users' => 'Supprimer des utilisateurs',
            'manage-rbac' => 'Gérer les rôles et permissions',
        ];
        
        return $descriptions[$this->name] ?? null;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource pour les Permissions.
 * Groupe les permissions par module pour faciliter l'affichage UI.
 * 
 * Exemple de réponse JSON:
 * {
 *   "data": [...],
 *   "grouped_by_module": {
 *     "users": [...],
 *     "contracts": [...]
 *   },
 *   "meta": {...}
 * }
 */
class PermissionCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => PermissionResource::collection($this->collection),
            
            // Groupement par module pour l'UI
            'grouped_by_module' => $this->collection->groupBy(fn($p) => 
                explode('-', $p->name)[1] ?? 'general'
            )->map(fn($permissions, $module) => [
                'module' => $module,
                'display_name' => $this->getModuleDisplayName($module),
                'permissions' => PermissionResource::collection($permissions),
            ])->values(),
            
            'meta' => $this->when($this->resource instanceof \Illuminate\Pagination\AbstractPaginator, function () {
                return [
                    'total' => $this->total(),
                    'per_page' => $this->perPage(),
                    'current_page' => $this->currentPage(),
                    'last_page' => $this->lastPage(),
                ];
            }),
            
            'summary' => [
                'total_permissions' => $this->collection->count(),
                'modules_count' => $this->collection->groupBy(fn($p) => 
                    explode('-', $p->name)[1] ?? 'general'
                )->count(),
            ],
        ];
    }

    private function getModuleDisplayName(string $module): string
    {
        $names = [
            'users' => 'Utilisateurs',
            'roles' => 'Rôles',
            'permissions' => 'Permissions',
            'contracts' => 'Contrats',
            'leaves' => 'Congés',
            'payslips' => 'Fiches de paie',
            'employees' => 'Employés',
            'reports' => 'Rapports',
        ];
        
        return $names[$module] ?? ucfirst($module);
    }
}

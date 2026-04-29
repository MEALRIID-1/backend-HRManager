<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur pour la gestion des permissions
 */
class PermissionController
{
    /**
     * Afficher la liste des permissions.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $permissions = Permission::with('roles')->get();

            return response()->json([
                'success' => true,
                'message' => 'Permissions récupérées avec succès.',
                'data' => $permissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des permissions.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Créer une nouvelle permission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'guard_name' => 'nullable|string',
        ]);

        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission créée avec succès.',
                'data' => $permission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la permission.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Afficher une permission spécifique.
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function show(Permission $permission): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Permission récupérée avec succès.',
                'data' => $permission->load('roles'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la permission.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mettre à jour une permission.
     *
     * @param Request $request
     * @param Permission $permission
     * @return JsonResponse
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
            'guard_name' => 'nullable|string',
        ]);

        try {
            $permission->update([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? $permission->guard_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission mise à jour avec succès.',
                'data' => $permission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la permission.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Supprimer une permission.
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function destroy(Permission $permission): JsonResponse
    {
        try {
            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission supprimée avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la permission.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Assigner des permissions à un rôle.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignToRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role = Role::findByName($request->role);
            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions assignées au rôle avec succès.',
                'data' => $role->load('permissions'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation des permissions.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Révoquer des permissions d'un rôle.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revokeFromRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role = Role::findByName($request->role);
            $role->revokePermissionTo($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions révoquées avec succès.',
                'data' => $role->load('permissions'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la révocation des permissions.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Récupérer les permissions par module.
     *
     * @return JsonResponse
     */
    public function byModule(): JsonResponse
    {
        try {
            $modules = [
                'employees' => Permission::where('name', 'like', '%employees%')->get(),
                'contracts' => Permission::where('name', 'like', '%contracts%')->get(),
                'conges' => Permission::where('name', 'like', '%leaves%')->orWhere('name', 'like', '%cong%')->get(),
                'fiches_paie' => Permission::where('name', 'like', '%payslip%')->orWhere('name', 'like', '%payroll%')->get(),
                'validations' => Permission::where('name', 'like', '%validations%')->get(),
                'reports' => Permission::where('name', 'like', '%reports%')->get(),
                'rbac' => Permission::where('name', 'like', '%roles%')
                    ->orWhere('name', 'like', '%permissions%')
                    ->orWhere('name', 'like', '%rbac%')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Permissions organisées par module.',
                'data' => $modules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des permissions.',
                'data' => null,
            ], 500);
        }
    }
}

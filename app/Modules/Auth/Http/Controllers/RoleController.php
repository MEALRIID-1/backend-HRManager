<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController
{
    /**
     * Display a listing of roles.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')->get();

            return response()->json([
                'success' => true,
                'message' => 'Rôles récupérés avec succès.',
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Store a newly created role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role = Role::create(['name' => $request->name]);

            if ($request->permissions) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rôle créé avec succès.',
                'data' => $role->load('permissions'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du rôle.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display the specified role.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function show(Role $role): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Rôle récupéré avec succès.',
                'data' => $role->load('permissions'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du rôle.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the specified role.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role->update(['name' => $request->name]);

            if ($request->permissions) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès.',
                'data' => $role->load('permissions'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du rôle.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rôle supprimé avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du rôle.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Assign role to user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
        ]);

        try {
            $user = User::find($request->user_id);
            $user->assignRole($request->role);

            return response()->json([
                'success' => true,
                'message' => 'Rôle assigné avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation du rôle.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Remove role from user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removeRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
        ]);

        try {
            $user = User::find($request->user_id);
            $user->removeRole($request->role);

            return response()->json([
                'success' => true,
                'message' => 'Rôle retiré avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait du rôle.',
                'data' => null,
            ], 500);
        }
    }
}
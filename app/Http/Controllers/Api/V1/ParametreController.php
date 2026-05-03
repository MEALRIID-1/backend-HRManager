<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateProfilRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Conge;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Validation;
use App\Services\ParametreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParametreController extends Controller
{
    public function __construct(
        private readonly ParametreService $parametreService,
    ) {
    }
    /**
     * Get all reference data (enums, types, etc.).
     */
    public function getReferences(): JsonResponse
    {
        try {
            $data = Cache::remember('parametres.references', 3600, function () {
                return [
                    'statuts_employe' => User::getStatuts(),
                    'types_conge' => Conge::getTypes(),
                    'statuts_conge' => Conge::getStatuts(),
                    'types_contrat' => Contrat::getTypes(),
                    'statuts_contrat' => Contrat::getStatuts(),
                    'statuts_validation' => Validation::getStatuts(),
                    'types_validation' => Validation::getTypes(),
                    'types_notification' => Notification::getTypes(),
                    'icones_notification' => Notification::getIcones(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur r+�cup+�ration r+�f+�rences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Get roles and permissions.
     */
    public function getRolesPermissions(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')->get();
            $permissions = Permission::all()->groupBy('module');

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur r+�cup+�ration r+�les/permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Get system configuration.
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = [
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'locale' => config('app.locale'),
                'timezone' => config('app.timezone'),
                'password_change_days' => config('hrmanager.password_change_days', 90),
                'max_login_attempts' => config('hrmanager.max_login_attempts', 5),
                'login_lockout_minutes' => config('hrmanager.login_lockout_minutes', 30),
            ];

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur r+�cup+�ration config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Récupérer son propre profil.
     */
    public function getProfil(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération profil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste des départements disponibles pour les sélecteurs.
     */
    public function getDepartements(): JsonResponse
    {
        try {
            $departements = User::query()
                ->whereNotNull('departement')
                ->where('departement', '!=', '')
                ->distinct()
                ->orderBy('departement')
                ->pluck('departement')
                ->values()
                ->map(fn (string $departement, int $index) => [
                    'id' => $index + 1,
                    'nom' => $departement,
                ]);

            return response()->json([
                'success' => true,
                'data' => $departements,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération départements: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Clear application cache.
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Cache effac+� avec succ+�s',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur effacement cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste tous les r+�les avec leurs permissions.
     */
    public function getRoles(): JsonResponse
    {
        try {
            $roles = Role::with(['permissions', 'users'])->get();

            return response()->json([
                'success' => true,
                'data' => RoleResource::collection($roles),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur r+�cup+�ration r+�les: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Cr+�er un nouveau r+�le.
     */
    public function createRole(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->parametreService->createRole($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'R+�le cr+�+� avec succ+�s',
                'data' => new RoleResource($role->load('permissions')),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur cr+�ation r+�le: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Modifier un r+�le.
     */
    public function updateRole(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'R+�le non trouv+�',
                ], 404);
            }

            $role = $this->parametreService->updateRole($role, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'R+�le mis +� jour avec succ+�s',
                'data' => new RoleResource($role),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur mise +� jour r+�le: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Supprimer un r+�le (soft delete).
     */
    public function deleteRole(int $id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'R+�le non trouv+�',
                ], 404);
            }

            $this->parametreService->deleteRole($role);

            return response()->json([
                'success' => true,
                'message' => 'R+�le supprim+� avec succ+�s',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression r+�le: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste toutes les permissions group+�es par module.
     */
    public function getPermissions(): JsonResponse
    {
        try {
            $permissions = $this->parametreService->getPermissionsByModule();

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur r+�cup+�ration permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Assigner des permissions +� un r+�le.
     */
    public function assignPermissionsToRole(Request $request, int $roleId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role = Role::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'R+�le non trouv+�',
                ], 404);
            }

            $this->parametreService->syncPermissions($role, $validated['permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Permissions assign+�es avec succ+�s',
                'data' => new RoleResource($role->fresh('permissions')),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur assignation permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Assigner un r+�le +� un employ+�.
     */
    public function assignRoleToUser(Request $request, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_id' => 'required|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouv+�',
                ], 404);
            }

            $this->parametreService->assignRoleToUser($user, $validated['role_id']);

            return response()->json([
                'success' => true,
                'message' => 'R+�le assign+� avec succ+�s',
                'data' => new UserResource($user->fresh('roles')),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur assignation r+�le: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Retirer un r+�le +� un employ+�.
     */
    public function revokeRoleFromUser(Request $request, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_id' => 'required|exists:roles,id',
            ]);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouv+�',
                ], 404);
            }

            $this->parametreService->revokeRoleFromUser($user, $validated['role_id']);

            return response()->json([
                'success' => true,
                'message' => 'R+�le retir+� avec succ+�s',
                'data' => new UserResource($user->fresh('roles')),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur r+�vocation r+�le: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Mettre +� jour son propre profil.
     */
    public function updateProfil(UpdateProfilRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user = $this->parametreService->updateProfil($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Profil mis +� jour avec succ+�s',
                'data' => new UserResource($user),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur mise +� jour profil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Upload photo de profil.
     */
    public function uploadPhotoProfil(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = $request->user();
            $user = $this->parametreService->uploadPhotoProfil($user, $validated['photo']);

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil mise +� jour',
                'data' => [
                    'photo_url' => $user->photo_profil ? Storage::disk('public')->url($user->photo_profil) : null,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur upload photo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Get system health status.
     */
    public function getHealthStatus(): JsonResponse
    {
        try {
            $status = [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ];

            $allHealthy = !in_array(false, $status, true);

            return response()->json([
                'success' => $allHealthy,
                'data' => $status,
            ], $allHealthy ? 200 : 503);
        } catch (\Exception $e) {
            Log::error('Erreur v+�rification sant+�: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Update system configuration (admin only).
     */
    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'password_change_days' => 'integer|min:1|max:365',
                'max_login_attempts' => 'integer|min:1|max:10',
                'login_lockout_minutes' => 'integer|min:1|max:1440',
            ]);

            foreach ($validated as $key => $value) {
                config(['hrmanager.' . $key => $value]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuration mise +� jour',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise +� jour config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    private function checkDatabase(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::put('health_check', 'ok', 10);
            return Cache::get('health_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        return is_writable(storage_path());
    }
}

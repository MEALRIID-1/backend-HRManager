<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Resources\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::where('email', $request->email)->first();
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants invalides.',
                    'data' => null,
                ], 401);
            }

            $user->loadMissing(['roles.permissions']);

            // ✅ Génération d’un token Bearer
            $abilities = $this->getAbilities($user);
            $token = $user->createToken('auth_token', $abilities)->plainTextToken;

            try {
                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'login',
                    'entity_name' => 'User',
                    'entity_id' => $user->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('ActivityLog create failed: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie.',
                'data' => new AuthResource($user, $token),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AuthController@login exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion.',
                'data' => null,
            ], 500);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            auth()->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion.',
                'data' => null,
            ], 500);
        }
    }

    public function logoutAll(): JsonResponse
    {
        try {
            auth()->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tous les tokens révoqués.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la révocation.',
                'data' => null,
            ], 500);
        }
    }

    public function me(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
                $user->load('roles.permissions');
            }

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur récupéré.',
                'data' => new AuthResource($user),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération.',
                'data' => null,
            ], 500);
        }
    }

    public function refreshToken(): JsonResponse
    {
        try {
            $user = auth()->user();
            $oldToken = $user->currentAccessToken();
            $abilities = $this->getAbilities($user);

            $newToken = $user->createToken('auth_token', $abilities)->plainTextToken;
            $oldToken->delete();

            return response()->json([
                'success' => true,
                'message' => 'Token rafraîchi.',
                'data' => new AuthResource($user, $newToken),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement.',
                'data' => null,
            ], 500);
        }
    }

    private function getAbilities(User $user): array
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('model_has_roles')) {
            return [];
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return ['*'];
        }

        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [];
    }
}

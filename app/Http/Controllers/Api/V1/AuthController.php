<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Connecter un utilisateur et retourner un token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->login(
                $validated,
                $validated['device_name'] ?? 'API Client'
            );

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur login: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Les identifiants sont incorrects',
            ], 401);
        }
    }

    /**
     * Déconnecter l'utilisateur (révoquer le token actuel).
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur logout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Retourner l'utilisateur connecté avec rôles et permissions.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userData = $this->authService->getUserWithRolesAndPermissions($user);

            return response()->json([
                'success' => true,
                'data' => $userData,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur me: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Changer le mot de passe de l'utilisateur.
     *
     * PUT /api/v1/auth/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $this->authService->changePassword(
                $request->user(),
                $validated['ancien_mot_de_passe'],
                $validated['nouveau_mot_de_passe']
            );

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur change password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

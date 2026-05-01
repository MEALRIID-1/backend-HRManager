<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeRequest;
use App\Http\Requests\UpdateEmployeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmployeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmployeController extends Controller
{
    public function __construct(
        private readonly EmployeService $employeService,
    ) {
    }

    /**
     * Liste paginée avec filtres.
     * Pour manager : filtre automatique sur son département.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['nom', 'departement', 'role', 'is_active']);
            $perPage = $request->integer('per_page', 15);

            // Récupérer l'utilisateur connecté pour vérifier si c'est un manager
            $currentUser = $request->user();

            $employes = $this->employeService->list($filters, $currentUser, $perPage);

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($employes),
                'meta' => [
                    'current_page' => $employes->currentPage(),
                    'last_page' => $employes->lastPage(),
                    'per_page' => $employes->perPage(),
                    'total' => $employes->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur liste employés: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Créer un employé avec génération automatique de mot de passe.
     *
     * @throws ValidationException
     */
    public function store(StoreEmployeRequest $request): JsonResponse
    {
        try {
            $employe = $this->employeService->createEmploye($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Employé créé avec succès. Un mot de passe temporaire a été généré.',
                'data' => new UserResource($employe),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Afficher un employé avec détails complets (contrat actif, solde congés, rôles).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $employe = $this->employeService->getEmployeWithDetails($id);

            if (!$employe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employé non trouvé',
                ], 404);
            }

            // Ajouter le solde de congés et le contrat actif
            $soldeConges = $this->employeService->calculerSoldeConges($employe);
            $contratActif = $this->employeService->getContratActif($employe);

            return response()->json([
                'success' => true,
                'data' => [
                    'employe' => new UserResource($employe),
                    'solde_conges' => $soldeConges,
                    'contrat_actif' => $contratActif,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur affichage employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Modifier un employé avec logs d'activité (old_value/new_value).
     *
     * @throws ValidationException
     */
    public function update(UpdateEmployeRequest $request, int $id): JsonResponse
    {
        try {
            $employe = User::find($id);

            if (!$employe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employé non trouvé',
                ], 404);
            }

            $employe = $this->employeService->update($employe, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Employé mis à jour avec succès',
                'data' => new UserResource($employe),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Soft delete d'un employé.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $employe = User::find($id);

            if (!$employe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employé non trouvé',
                ], 404);
            }

            $this->employeService->destroy($employe);

            return response()->json([
                'success' => true,
                'message' => 'Employé déplacé vers la corbeille',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Restaurer un employé supprimé (corbeille).
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $employe = $this->employeService->restore($id);

            return response()->json([
                'success' => true,
                'message' => 'Employé restauré avec succès',
                'data' => new UserResource($employe),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur restauration employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste des employés en corbeille (soft deleted).
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $employes = $this->employeService->trashed($perPage);

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($employes),
                'meta' => [
                    'current_page' => $employes->currentPage(),
                    'last_page' => $employes->lastPage(),
                    'per_page' => $employes->perPage(),
                    'total' => $employes->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur liste corbeille: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Suppression définitive (admin seulement).
     */
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->employeService->forceDelete($id);

            return response()->json([
                'success' => true,
                'message' => 'Employé supprimé définitivement',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression définitive: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Upload photo de profil.
     */
    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'photo' => ['required', 'image', 'max:2048'], // Max 2MB
            ]);

            $employe = User::find($id);

            if (!$employe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employé non trouvé',
                ], 404);
            }

            $path = $this->employeService->uploadPhoto($employe, $validated['photo']);

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil mise à jour',
                'data' => [
                    'photo_url' => asset('storage/' . $path),
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur upload photo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

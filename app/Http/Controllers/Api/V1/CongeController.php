<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCongeRequest;
use App\Http\Requests\UpdateCongeRequest;
use App\Http\Requests\ValiderCongeRequest;
use App\Http\Resources\CongeResource;
use App\Models\Conge;
use App\Services\CongeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CongeController extends Controller
{
    public function __construct(
        private readonly CongeService $congeService,
    ) {
    }

    /**
     * Liste paginée avec filtres (statut, type, date, employé).
     * Scope par rôle automatique.
     */
    public function index(Request $request): JsonResponse
    {
        // try {
            // Filtres supportés: ?search=, ?statut=, ?type=, ?date_debut=, ?date_fin=, ?employe_id=, ?per_page=15
            $filters = $request->only(['statut', 'type', 'date_debut', 'date_fin', 'employe_id']);
            $perPage = $request->integer('per_page', 15);
            $currentUser = $request->user();

            $conges = $this->congeService->list($filters, $currentUser, $perPage);

            return response()->json([
                'success' => true,
                'data' => CongeResource::collection($conges),
                'meta' => [
                    'current_page' => $conges->currentPage(),
                    'last_page' => $conges->lastPage(),
                    'per_page' => $conges->perPage(),
                    'total' => $conges->total(),
                    'from' => $conges->firstItem(),
                    'to' => $conges->lastItem(),
                ],
            ], 200);
        // } catch (\Exception $e) {
        //     Log::error('Erreur liste congés: ' . $e->getMessage());
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Une erreur est survenue',
        //     ], 500);
        // }
    }

    /**
     * Créer une demande de congé (employé seulement).
     * Déclenche notifications aux validateurs.
     *
     * @throws ValidationException
     */
    public function store(StoreCongeRequest $request): JsonResponse
    {
        try {
            $conge = $this->congeService->create(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Demande de congé créée avec succès',
                'data' => new CongeResource($conge),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création congé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Modifier une demande (seulement si etat=en_attente).
     *
     * @throws ValidationException
     */
    public function update(UpdateCongeRequest $request, int $id): JsonResponse
    {
        try {
            $conge = Conge::find($id);

            if (!$conge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de congé non trouvée',
                ], 404);
            }

            // Vérifier que c'est le propriétaire
            if ($conge->employe_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $conge = $this->congeService->update($conge, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Demande de congé mise à jour',
                'data' => new CongeResource($conge),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour congé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Soft delete d'une demande de congé.
     */
    public function destroy(int $id): JsonResponse
    {
        // try {
            $conge = Conge::find($id);

            if (!$conge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de congé non trouvée',
                ], 404);
            }

            // Vérifier que c'est le propriétaire ou un admin/RH
            $user = auth()->user();
            if ($conge->employe_id !== $user->id && !$user->roles->contains('nom', 'Administrateur') && !$user->roles->contains('nom', 'rh')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $this->congeService->destroy($conge);

            return response()->json([
                'success' => true,
                'message' => 'Demande de congé supprimée',
            ], 200);
        // } catch (ValidationException $e) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Erreur de validation',
        //         'errors' => $e->errors(),
        //     ], 422);
        // } 
        // catch (\Exception $e) {
        //     Log::error('Erreur suppression congé: ' . $e->getMessage());
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Une erreur est survenue',
        //     ], 500);
        // }
    }

    /**
     * Restaurer un congé supprimé.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $conge = $this->congeService->restore($id);

            return response()->json([
                'success' => true,
                'message' => 'Demande de congé restaurée',
                'data' => new CongeResource($conge),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur restauration congé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste des congés en corbeille.
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $conges = $this->congeService->trashed($perPage);

            return response()->json([
                'success' => true,
                'data' => CongeResource::collection($conges),
                'meta' => [
                    'current_page' => $conges->currentPage(),
                    'last_page' => $conges->lastPage(),
                    'per_page' => $conges->perPage(),
                    'total' => $conges->total(),
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
     * Valider ou refuser une demande de congé.
     * Motif obligatoire. Enregistre dans validations table.
     *
     * @throws ValidationException
     */
    public function valider(ValiderCongeRequest $request, int $id): JsonResponse
    {
        // try {
            $conge = Conge::find($id);

            if (!$conge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de congé non trouvée',
                ], 404);
            }

            $validateur = $request->user();

            if (!$validateur || !$this->congeService->peutValider($validateur, $conge)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à valider ce congé',
                ], 403);
            }

            $niveau = $this->congeService->getNiveauValidation($validateur);

            if (!$niveau) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à valider ce congé',
                ], 403);
            }

            $this->congeService->valider(
                $conge,
                $validateur,
                $request->validated('decision'),
                $request->validated('motif'),
                $niveau
            );

            return response()->json([
                'success' => true,
                'message' => 'Validation enregistrée avec succès',
                'data' => new CongeResource($conge->fresh()),
            ], 200);
        // } catch (ValidationException $e) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Erreur de validation',
        //         'errors' => $e->errors(),
        //     ], 422);
        // } catch (\Exception $e) {
        //     Log::error('Erreur validation congé: ' . $e->getMessage());
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Une erreur est survenue',
        //     ], 500);
        // }
    }

    /**
     * Super validation (admin seulement).
     * Valide tous les niveaux d'un coup.
     *
     * @throws ValidationException
     */
    public function superValidation(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'motif' => ['required', 'string', 'min:10'],
            ]);

            $conge = Conge::find($id);

            if (!$conge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de congé non trouvée',
                ], 404);
            }

            $this->congeService->superValidation($conge, $request->user(), $validated['motif']);

            return response()->json([
                'success' => true,
                'message' => 'Super validation effectuée avec succès',
                'data' => new CongeResource($conge->fresh()),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur super validation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste des congés de l'employé connecté.
     */
    public function getMyConges(Request $request): JsonResponse
    {
        try {
            $conges = $this->congeService->getMyConges($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => CongeResource::collection($conges),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération mes congés: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Obtenir le solde de congés.
     */
    public function getSolde(Request $request): JsonResponse
    {
        try {
            $solde = $this->congeService->getSoldeConges($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $solde,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération solde: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContratRequest;
use App\Http\Requests\UpdateContratRequest;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use App\Services\ContratService;
use App\Services\ParametreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContratController extends Controller
{
    public function __construct(
        private readonly ContratService $contratService,
        private readonly ParametreService $parametreService,
    ) {
    }

    /**
     * Liste paginée avec filtres (type, etat, employé, date).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Filtres supportés: ?type=, ?etat=, ?employe_id=, ?expirant_bientot=1, ?jours=30, ?per_page=15
            $filters = $request->only(['type', 'etat', 'employe_id', 'expirant_bientot', 'jours']);
            $perPage = $request->integer('per_page', 15);

            $contrats = $this->contratService->list($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => ContratResource::collection($contrats),
                'meta' => [
                    'current_page' => $contrats->currentPage(),
                    'last_page' => $contrats->lastPage(),
                    'per_page' => $contrats->perPage(),
                    'total' => $contrats->total(),
                    'from' => $contrats->firstItem(),
                    'to' => $contrats->lastItem(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur liste contrats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Créer un contrat avec validation métier (un seul contrat actif par employé).
     *
     * @throws ValidationException
     */
    public function store(StoreContratRequest $request): JsonResponse
    {
        try {
            $contrat = $this->contratService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Contrat créé avec succès',
                'data' => new ContratResource($contrat),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Afficher un contrat avec données employé pour génération de lettre officielle.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contrat = $this->contratService->getContratWithDetails($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ContratResource($contrat),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur affichage contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Modifier un contrat avec logs.
     *
     * @throws ValidationException
     */
    public function update(UpdateContratRequest $request, int $id): JsonResponse
    {
        try {
            $contrat = Contrat::find($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            $contrat = $this->contratService->update($contrat, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Contrat mis à jour',
                'data' => new ContratResource($contrat),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Soft delete d'un contrat.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $contrat = Contrat::find($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            $this->contratService->destroy($contrat);

            return response()->json([
                'success' => true,
                'message' => 'Contrat supprimé',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Restaurer un contrat supprimé.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $contrat = $this->contratService->restore($id);

            return response()->json([
                'success' => true,
                'message' => 'Contrat restauré',
                'data' => new ContratResource($contrat),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur restauration contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Liste des contrats en corbeille.
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $contrats = $this->contratService->trashed($perPage);

            return response()->json([
                'success' => true,
                'data' => ContratResource::collection($contrats),
                'meta' => [
                    'current_page' => $contrats->currentPage(),
                    'last_page' => $contrats->lastPage(),
                    'per_page' => $contrats->perPage(),
                    'total' => $contrats->total(),
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
     * Générer le PDF du contrat sous forme de lettre officielle.
     */
    public function genererPDF(int $id): JsonResponse
    {
        try {
            $contrat = Contrat::find($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            $path = $this->contratService->genererPDFContrat($contrat);

            return response()->json([
                'success' => true,
                'message' => 'PDF généré avec succès',
                'data' => [
                    'pdf_url' => asset('storage/' . $path),
                    'path' => $path,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération du PDF',
            ], 500);
        }
    }

    /**
     * Télécharger le PDF du contrat (admin et RH seulement).
     */
    public function telecharger(int $id, Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            // Vérifier la permission contrats.download
            if (!$this->parametreService->canAccess($request->user(), 'contrats.download')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission de télécharger les contrats',
                ], 403);
            }

            $contrat = Contrat::find($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            // Vérifier si un PDF existe déjà
            $pdfPath = 'contrats/contrat_' . $contrat->id . '_' . $contrat->employe->nom . '.pdf';

            if (!Storage::disk('public')->exists($pdfPath)) {
                // Générer le PDF s'il n'existe pas
                $pdfPath = $this->contratService->genererPDFContrat($contrat);
            }

            $fullPath = $this->contratService->telechargerPDF($pdfPath);

            if (!$fullPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF non trouvé',
                ], 404);
            }

            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="contrat_' . $contrat->id . '.pdf"',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur téléchargement PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Impression du contrat (admin et RH seulement).
     * Retourne le PDF en inline pour l'impression.
     */
    public function imprimer(int $id, Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            // Vérifier la permission contrats.print
            if (!$this->parametreService->canAccess($request->user(), 'contrats.print')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission d\'imprimer les contrats',
                ], 403);
            }

            $contrat = Contrat::find($id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé',
                ], 404);
            }

            // Vérifier si un PDF existe déjà
            $pdfPath = 'contrats/contrat_' . $contrat->id . '_' . $contrat->employe->nom . '.pdf';

            if (!Storage::disk('public')->exists($pdfPath)) {
                // Générer le PDF s'il n'existe pas
                $pdfPath = $this->contratService->genererPDFContrat($contrat);
            }

            $fullPath = $this->contratService->telechargerPDF($pdfPath);

            if (!$fullPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF non trouvé',
                ], 404);
            }

            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="contrat_' . $contrat->id . '.pdf"',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur impression contrat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Retourner le contrat actif d'un employé.
     */
    public function getContratActif(int $employeId): JsonResponse
    {
        try {
            $contrat = $this->contratService->getContratActif($employeId);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun contrat actif trouvé pour cet employé',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ContratResource($contrat),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur récupération contrat actif: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

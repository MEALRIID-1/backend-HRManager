<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFichePaieRequest;
use App\Http\Resources\FichePaieResource;
use App\Models\FichePaie;
use App\Services\FichePaieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FichePaieController extends Controller
{
    public function __construct(
        private readonly FichePaieService $fichePaieService,
    ) {
    }

    /**
     * Liste paginée des fiches de paie avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['mois', 'annee', 'employe_id', 'statut']);
            $perPage = $request->integer('per_page', 15);

            $fiches = $this->fichePaieService->list($filters, $perPage);

            return ApiResponse::paginated($fiches, 'Fiches de paie récupérées avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur liste fiches de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Créer une fiche de paie (RH/Admin).
     */
    public function store(StoreFichePaieRequest $request): JsonResponse
    {
        try {
            // Vérifier doublon
            if ($this->fichePaieService->verifierFicheExistante(
                $request->validated()['employe_id'],
                $request->validated()['mois'],
                $request->validated()['annee']
            )) {
                return ApiResponse::error(
                    'Une fiche de paie existe déjà pour cet employé ce mois-ci',
                    null,
                    422
                );
            }

            $fiche = $this->fichePaieService->create($request->user(), $request->validated());

            return ApiResponse::created(
                new FichePaieResource($fiche->load('employe')),
                'Fiche de paie créée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur création fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Détail d'une fiche de paie avec calculs.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::with(['employe'])->find($id);

            if (!$fiche) {
                return ApiResponse::notFound('Fiche de paie non trouvée');
            }

            // Calculs détaillés
            $calculs = $this->fichePaieService->calculerNetAPayer(
                $fiche->salaire_base,
                $fiche->heures_sup,
                $fiche->absences
            );

            return ApiResponse::success([
                'fiche' => new FichePaieResource($fiche),
                'calculs' => $calculs,
            ], 'Fiche de paie récupérée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur affichage fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Modifier une fiche de paie.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);

            if (!$fiche) {
                return ApiResponse::notFound('Fiche de paie non trouvée');
            }

            $validated = $request->validate([
                'salaire_base' => 'sometimes|numeric|min:0',
                'heures_sup' => 'sometimes|numeric|min:0',
                'absences' => 'sometimes|integer|min:0',
                'statut' => 'sometimes|in:brouillon,finalisee,payee',
            ]);

            $fiche = $this->fichePaieService->update($fiche, $validated);

            return ApiResponse::success(
                new FichePaieResource($fiche->load('employe')),
                'Fiche de paie mise à jour avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Supprimer une fiche de paie (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);

            if (!$fiche) {
                return ApiResponse::notFound('Fiche de paie non trouvée');
            }

            $this->fichePaieService->delete($fiche);

            return ApiResponse::success(null, 'Fiche de paie supprimée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur suppression fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Restaurer une fiche de paie.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $fiche = $this->fichePaieService->restore($id);

            return ApiResponse::success(
                new FichePaieResource($fiche->load('employe')),
                'Fiche de paie restaurée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur restauration fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Lister les fiches supprimées (corbeille).
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $fiches = $this->fichePaieService->getTrashed($perPage);

            return ApiResponse::paginated($fiches, 'Corbeille récupérée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur récupération corbeille: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Générer le PDF d'une fiche de paie.
     */
    public function genererPDF(int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);

            if (!$fiche) {
                return ApiResponse::notFound('Fiche de paie non trouvée');
            }

            $path = $this->fichePaieService->genererFichePDF($fiche);

            return ApiResponse::success([
                'pdf_url' => Storage::disk('public')->url($path),
                'path' => $path,
            ], 'PDF généré avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Télécharger le PDF d'une fiche de paie.
     */
    public function telecharger(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);

            if (!$fiche) {
                return ApiResponse::notFound('Fiche de paie non trouvée');
            }

            // Générer si pas encore de PDF
            if (!$fiche->pdf_url) {
                $this->fichePaieService->genererFichePDF($fiche);
                $fiche->refresh();
            }

            if (!$fiche->pdf_url || !Storage::disk('public')->exists($fiche->pdf_url)) {
                return ApiResponse::error('PDF non trouvé', null, 404);
            }

            return Storage::disk('public')->download(
                $fiche->pdf_url,
                "fiche-paie-{$fiche->employe->nom}-{$fiche->mois}-{$fiche->annee}.pdf"
            );
        } catch (\Exception $e) {
            Log::error('Erreur téléchargement fiche de paie: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }

    /**
     * Récupérer les fiches de l'employé connecté.
     */
    public function getMesFiches(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $fiches = $this->fichePaieService->getMesFiches($request->user()->id, $perPage);

            return ApiResponse::paginated($fiches, 'Mes fiches de paie récupérées avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur récupération mes fiches: ' . $e->getMessage());
            return ApiResponse::serverError('Une erreur est survenue');
        }
    }
}

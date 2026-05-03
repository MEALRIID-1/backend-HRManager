<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFichePaieRequest;
use App\Http\Resources\FichePaieResource;
use App\Models\FichePaie;
use App\Services\FichePaieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FichePaieController extends Controller
{
    public function __construct(
        private readonly FichePaieService $fichePaieService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['mois', 'annee', 'employe_id', 'statut']);
            $perPage = $request->integer('per_page', 15);
            $fiches = $this->fichePaieService->list($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Fiches de paie récupérées avec succès',
                'data' => FichePaieResource::collection($fiches)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur liste fiches: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(StoreFichePaieRequest $request): JsonResponse
    {
        try {
            $fiche = $this->fichePaieService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Fiche de paie créée avec succès',
                'data' => new FichePaieResource($fiche->load('employe'))
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur création fiche: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::with(['employe'])->find($id);
            if (!$fiche) {
                return response()->json(['success' => false, 'message' => 'Fiche non trouvée'], 404);
            }

            return response()->json(['success' => true, 'data' => new FichePaieResource($fiche)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);
            if (!$fiche) {
                return response()->json(['success' => false, 'message' => 'Fiche non trouvée'], 404);
            }

            $fiche = $this->fichePaieService->update($fiche, $request->all());
            return response()->json(['success' => true, 'data' => new FichePaieResource($fiche)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);
            if (!$fiche) {
                return response()->json(['success' => false, 'message' => 'Fiche non trouvée'], 404);
            }

            $this->fichePaieService->delete($fiche);
            return response()->json(['success' => true, 'message' => 'Fiche supprimée']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $fiche = $this->fichePaieService->restore($id);
            return response()->json(['success' => true, 'data' => new FichePaieResource($fiche)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $fiches = $this->fichePaieService->getTrashed($perPage);
            return response()->json(['success' => true, 'data' => FichePaieResource::collection($fiches)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function telecharger(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);
            if (!$fiche) {
                return response()->json(['success' => false, 'message' => 'Fiche non trouvée'], 404);
            }

            if (!$fiche->document_path) {
                $this->fichePaieService->genererFichePDF($fiche);
                $fiche->refresh();
            }

            $pdfPath = storage_path('app/public/' . $fiche->document_path);
            if (!file_exists($pdfPath)) {
                return response()->json(['success' => false, 'message' => 'PDF non trouvé'], 404);
            }

            return response()->download($pdfPath, "bulletin-paie-{$fiche->periode}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur téléchargement: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function genererPDF(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $fiche = FichePaie::find($id);
            if (!$fiche) {
                return response()->json(['success' => false, 'message' => 'Fiche non trouvée'], 404);
            }

            $this->fichePaieService->genererFichePDF($fiche);
            $fiche->refresh();

            $pdfPath = storage_path('app/public/' . $fiche->document_path);
            if (!file_exists($pdfPath)) {
                return response()->json(['success' => false, 'message' => 'PDF non généré'], 500);
            }

            return response()->download($pdfPath, "bulletin-paie-{$fiche->periode}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getMesFiches(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $fiches = $this->fichePaieService->getMesFiches($request->user()->id, $perPage);
            return response()->json(['success' => true, 'data' => FichePaieResource::collection($fiches)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
}
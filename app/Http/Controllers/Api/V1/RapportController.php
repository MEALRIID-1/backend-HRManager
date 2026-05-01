<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\CongeResource;
use App\Http\Resources\UserResource;
use App\Services\RapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;

class RapportController extends Controller
{
    public function __construct(
        private readonly RapportService $rapportService,
    ) {
    }

    /**
     * Rapport détaillé des congés avec filtres.
     */
    public function rapportConges(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_debut',
                'date_fin',
                'departement',
                'type',
                'etat',
            ]);

            $data = $this->rapportService->generateCongesReport($filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => $data['periode'],
                    'statistiques' => $data['statistiques'],
                    'par_type' => $data['par_type'],
                    'par_departement' => $data['par_departement'],
                    'conges' => CongeResource::collection($data['conges']),
                    'filtres_appliques' => $data['filtres_appliques'],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur rapport congés: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Rapport des employés (embauches, départs, contrats).
     */
    public function rapportEmployes(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_embauche_debut',
                'date_embauche_fin',
                'departement',
                'is_active',
            ]);

            $data = $this->rapportService->generateEmployesReport($filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => $data['periode'],
                    'statistiques' => $data['statistiques'],
                    'par_departement' => $data['par_departement'],
                    'employes' => UserResource::collection($data['employes']),
                    'filtres_appliques' => $data['filtres_appliques'],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur rapport employés: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Rapport d'activité/logs (admin seulement).
     */
    public function rapportActivite(Request $request): JsonResponse
    {
        try {
            // Vérifier que c'est un admin
            if (!$request->user()->roles->contains('nom', 'admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $filters = $request->only([
                'user_id',
                'entity_name',
                'action',
                'date_from',
                'date_to',
            ]);

            $data = $this->rapportService->generateActivityReport($filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => $data['periode'],
                    'statistiques' => $data['statistiques'],
                    'par_entite' => $data['par_entite'],
                    'par_action' => $data['par_action'],
                    'par_utilisateur' => $data['par_utilisateur'],
                    'activites' => ActivityLogResource::collection($data['activites']),
                    'filtres_appliques' => $data['filtres_appliques'],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur rapport activité: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Export PDF du rapport.
     */
    public function exportPDF(string $type, Request $request)
    {
        try {
            $filters = $request->all();

            // Générer les données selon le type
            $data = match ($type) {
                'conges' => $this->rapportService->generateCongesReport($filters),
                'employes' => $this->rapportService->generateEmployesReport($filters),
                'activite' => $this->rapportService->generateActivityReport($filters),
                default => throw new \InvalidArgumentException("Type de rapport inconnu: {$type}"),
            };

            // Générer le PDF
            $pdf = Pdf::loadView("pdf.rapports.{$type}", ['data' => $data]);
            $pdf->setPaper('A4', 'landscape');

            $filename = "rapport_{$type}_" . now()->format('Ymd_His') . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Erreur export PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
            ], 500);
        }
    }

    /**
     * Export Excel du rapport.
     */
    public function exportExcel(string $type, Request $request)
    {
        try {
            $filters = $request->all();

            // Générer les données selon le type
            $data = match ($type) {
                'conges' => $this->rapportService->generateCongesReport($filters),
                'employes' => $this->rapportService->generateEmployesReport($filters),
                'activite' => $this->rapportService->generateActivityReport($filters),
                default => throw new \InvalidArgumentException("Type de rapport inconnu: {$type}"),
            };

            $filename = "rapport_{$type}_" . now()->format('Ymd_His') . '.xlsx';

            // Utiliser Maatwebsite Excel pour générer le fichier
            return Excel::download(
                new \App\Exports\RapportExport($type, $data),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Erreur export Excel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du fichier Excel',
            ], 500);
        }
    }

    /**
     * Nettoyer le cache des rapports.
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->rapportService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Cache des rapports nettoyé',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur nettoyage cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

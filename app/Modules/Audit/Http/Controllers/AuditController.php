<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pour l'audit trail (Admin/RH uniquement).
 */
class AuditController
{
    private ActivityLogService $logService;

    public function __construct(ActivityLogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Liste paginée des logs avec filtres avancés.
     *
     * GET /api/audit/logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Vérification des permissions
            if (!$request->user()->hasRole(['admin', 'rh', 'directeur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $filters = [
                'user_id' => $request->input('user_id'),
                'entity_name' => $request->input('entity_name'),
                'entity_id' => $request->input('entity_id'),
                'action' => $request->input('action'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'search' => $request->input('search'),
            ];

            $perPage = $request->input('per_page', 25);
            $logs = $this->logService->search(array_filter($filters), $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs->items(),
                    'pagination' => [
                        'total' => $logs->total(),
                        'per_page' => $logs->perPage(),
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Timeline d'une entité spécifique.
     *
     * GET /api/audit/timeline/{entity}/{id}
     */
    public function timeline(string $entity, int $id): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'rh'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $logs = ActivityLog::parEntite(ucfirst($entity))
                ->parEntityId($id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'entity' => $entity,
                    'entity_id' => $id,
                    'timeline' => $logs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la timeline.',
            ], 500);
        }
    }

    /**
     * Activité d'un utilisateur.
     *
     * GET /api/audit/user/{user}/activity
     */
    public function userActivity(Request $request, int $userId): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'rh']) && auth()->id() !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $from = $request->input('from') 
                ? Carbon::parse($request->input('from')) 
                : now()->subDays(30);
            $to = $request->input('to') 
                ? Carbon::parse($request->input('to')) 
                : now();

            $logs = $this->logService->getUserActivity(
                \App\Models\User::findOrFail($userId),
                $from,
                $to,
                $request->input('action')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'period' => [
                        'from' => $from->toDateString(),
                        'to' => $to->toDateString(),
                    ],
                    'activities' => $logs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'activité.',
            ], 500);
        }
    }

    /**
     * Statistiques d'audit.
     *
     * GET /api/audit/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'rh', 'directeur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $days = $request->input('days', 30);
            $stats = $this->logService->getStats($days);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques.',
            ], 500);
        }
    }

    /**
     * Types d'entités disponibles.
     *
     * GET /api/audit/entities
     */
    public function entities(): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'rh'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $entities = ActivityLog::select('entity_name')
                ->distinct()
                ->orderBy('entity_name')
                ->pluck('entity_name');

            return response()->json([
                'success' => true,
                'data' => $entities,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des entités.',
            ], 500);
        }
    }

    /**
     * Actions disponibles.
     *
     * GET /api/audit/actions
     */
    public function actions(): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'rh'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $actions = ActivityLog::select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action');

            return response()->json([
                'success' => true,
                'data' => $actions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions.',
            ], 500);
        }
    }
}

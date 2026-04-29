<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    /**
     * Liste paginée des notifications de l'utilisateur connecté.
     *
     * GET /api/notifications
     * Paramètres: page, per_page, type, read_status (all|read|unread)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            $query = Notification::pourUtilisateur($userId);

            // Filtre par statut lu/non-lu
            $readStatus = $request->input('read_status', 'all');
            if ($readStatus === 'unread') {
                $query->nonLues();
            } elseif ($readStatus === 'read') {
                $query->lues();
            }

            // Filtre par type
            if ($request->has('type')) {
                $query->parType($request->input('type'));
            }

            // Filtre par priorité
            if ($request->has('priority')) {
                $query->parPriorite($request->input('priority'));
            }

            $perPage = $request->input('per_page', 15);
            $notifications = $query
                ->orderByRaw('read_at IS NULL DESC') // Non lues d'abord
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications->items(),
                    'meta' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue.
     *
     * POST /api/notifications/{notification}/read
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        try {
            // Vérifier que la notification appartient à l'utilisateur connecté
            if ($notification->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $notification->marquerCommeLue();

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue.',
                'data' => [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues.
     *
     * POST /api/notifications/read-all
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $count = Notification::marquerToutCommeLue($userId);

            return response()->json([
                'success' => true,
                'message' => "{$count} notification(s) marquée(s) comme lue(s).",
                'data' => [
                    'marked_as_read' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir le nombre de notifications non lues (endpoint léger pour badge).
     *
     * GET /api/notifications/unread-count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            $count = Notification::pourUtilisateur($userId)
                ->nonLues()
                ->count();

            // Compter aussi par type pour afficher des badges spécifiques
            $countByType = Notification::compterParType($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_unread' => $count,
                    'by_type' => $countByType,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une notification.
     *
     * DELETE /api/notifications/{notification}
     */
    public function destroy(Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les notifications récentes (pour dropdown/header).
     *
     * GET /api/notifications/recent
     */
    public function getRecent(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $limit = $request->input('limit', 5);

            $notifications = Notification::pourUtilisateur($userId)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Liste des notifications de l'utilisateur connecté (non lues en premier).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);

            $notifications = $this->notificationService->getNotifications($request->user()->id, $perPage);

            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'non_lues' => $this->notificationService->getUnreadCount($request->user()->id),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur liste notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Display the specified notification.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $notification = Notification::where('user_id', $request->user()->id)
                ->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new NotificationResource($notification),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur affichage notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id, Request $request): JsonResponse
    {
        try {
            $notification = Notification::where('user_id', $request->user()->id)
                ->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée',
                ], 404);
            }

            $this->notificationService->markAsRead($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'data' => new NotificationResource($notification->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur marquage notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Tout marquer comme lu.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "{$count} notification(s) marquée(s) comme lue(s)",
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur marquage notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Supprimer une notification.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        try {
            $notification = Notification::where('user_id', $request->user()->id)
                ->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée',
                ], 404);
            }

            $this->notificationService->delete($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur suppression notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Nombre de notifications non lues (pour le badge).
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->getUnreadCount($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'non_lues' => $count,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur comptage notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}

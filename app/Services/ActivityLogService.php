<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    /**
     * Enregistrer une action dans les logs.
     *
     * @param string $action Type d'action (create, update, delete, etc.)
     * @param string $entity Nom de l'entité concernée
     * @param mixed $oldValue Valeur avant modification
     * @param mixed $newValue Valeur après modification
     * @return ActivityLog|null
     */
    public function log(string $action, string $entity, mixed $oldValue, mixed $newValue): ?ActivityLog
    {
        try {
            return ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity_name' => $entity,
                'old_value' => $oldValue ? json_encode($oldValue) : null,
                'new_value' => $newValue ? json_encode($newValue) : null,
                'timestamp' => now(),
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du log d\'activité: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer l'historique avec filtres.
     *
     * @param array<string, mixed> $filters Filtres (qui, quoi, quand, où)
     * @param int $perPage Nombre d'éléments par page
     * @return LengthAwarePaginator
     */
    public function getHistorique(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ActivityLog::with(['user']);

        // Filtre par utilisateur (qui)
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filtre par entité (quoi)
        if (isset($filters['entity_name'])) {
            $query->where('entity_name', $filters['entity_name']);
        }

        // Filtre par action (quoi)
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Filtre par date (quand)
        if (isset($filters['date_from'])) {
            $query->where('timestamp', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('timestamp', '<=', $filters['date_to']);
        }

        // Filtre par IP (où)
        if (isset($filters['ip_address'])) {
            $query->where('ip_address', 'like', '%' . $filters['ip_address'] . '%');
        }

        return $query->orderBy('timestamp', 'desc')->paginate($perPage);
    }

    /**
     * Récupérer l'historique d'une entité spécifique.
     *
     * @param string $entityName Nom de l'entité
     * @param int $entityId ID de l'entité
     * @param int $perPage Nombre d'éléments par page
     * @return LengthAwarePaginator
     */
    public function getHistoriqueEntity(string $entityName, int $entityId, int $perPage = 15): LengthAwarePaginator
    {
        return ActivityLog::with(['user'])
            ->where('entity_name', $entityName)
            ->whereRaw("JSON_EXTRACT(old_value, '$.id') = ? OR JSON_EXTRACT(new_value, '$.id') = ?", [$entityId, $entityId])
            ->orderBy('timestamp', 'desc')
            ->paginate($perPage);
    }

    /**
     * Récupérer l'historique d'un utilisateur.
     *
     * @param int $userId ID de l'utilisateur
     * @param int $perPage Nombre d'éléments par page
     * @return LengthAwarePaginator
     */
    public function getHistoriqueUtilisateur(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return ActivityLog::with(['user'])
            ->where('user_id', $userId)
            ->orderBy('timestamp', 'desc')
            ->paginate($perPage);
    }
}

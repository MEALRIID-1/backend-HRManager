<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Service pour la gestion des logs d'activité.
 * Centralise l'enregistrement des actions sur les modèles.
 */
class ActivityLogService
{
    /**
     * Enregistrer une action dans les logs.
     *
     * @param string $action Type d'action (created, updated, deleted, etc.)
     * @param Model $entity Entité modifiée
     * @param array $oldValues Valeurs avant modification
     * @param array $newValues Valeurs après modification
     * @param string|null $description Description optionnelle
     * @return ActivityLog
     */
    public function log(
        string $action,
        Model $entity,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_name' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'old_values' => ActivityLog::filterSensitive($oldValues),
            'new_values' => ActivityLog::filterSensitive($newValues),
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Récupérer l'historique d'une entité spécifique.
     *
     * @param Model $entity Entité concernée
     * @param int|null $limit Limite de résultats
     * @return \Illuminate\Support\Collection
     */
    public function getTimeline(Model $entity, ?int $limit = null): \Illuminate\Support\Collection
    {
        $query = ActivityLog::parEntite(class_basename($entity))
            ->parEntityId($entity->getKey())
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Récupérer l'activité d'un utilisateur sur une période.
     *
     * @param User $user Utilisateur concerné
     * @param Carbon $from Date de début
     * @param Carbon $to Date de fin
     * @param string|null $action Filtrer par type d'action
     * @return \Illuminate\Support\Collection
     */
    public function getUserActivity(
        User $user,
        Carbon $from,
        Carbon $to,
        ?string $action = null
    ): \Illuminate\Support\Collection {
        $query = ActivityLog::parUser($user->id)
            ->entrePlages($from, $to)
            ->with(['user:id,name'])
            ->orderByDesc('created_at');

        if ($action) {
            $query->parAction($action);
        }

        return $query->get();
    }

    /**
     * Récupérer les statistiques d'activité.
     *
     * @param int $days Nombre de jours à analyser
     * @return array
     */
    public function getStats(int $days = 30): array
    {
        $from = now()->subDays($days);
        $to = now();

        return [
            'total_actions' => ActivityLog::entrePlages($from, $to)->count(),
            'par_action' => ActivityLog::entrePlages($from, $to)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'par_entite' => ActivityLog::entrePlages($from, $to)
                ->selectRaw('entity_name, COUNT(*) as count')
                ->groupBy('entity_name')
                ->pluck('count', 'entity_name')
                ->toArray(),
            'utilisateurs_actifs' => ActivityLog::entrePlages($from, $to)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    /**
     * Rechercher dans les logs avec filtres avancés.
     *
     * @param array $filters Filtres de recherche
     * @param int $perPage Résultats par page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function search(array $filters, int $perPage = 25): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,name,email');

        if (!empty($filters['user_id'])) {
            $query->parUser($filters['user_id']);
        }

        if (!empty($filters['entity_name'])) {
            $query->parEntite($filters['entity_name']);
        }

        if (!empty($filters['entity_id'])) {
            $query->parEntityId($filters['entity_id']);
        }

        if (!empty($filters['action'])) {
            $query->parAction($filters['action']);
        }

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->entrePlages(
                Carbon::parse($filters['from']),
                Carbon::parse($filters['to'])
            );
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('entity_name', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}

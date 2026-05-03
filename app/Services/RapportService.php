<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Conge;
use App\Models\Contrat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RapportService
{
    /**
     * Générer rapport détaillé des congés avec filtres.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generateCongesReport(array $filters = []): array
    {
        try {
            $cacheKey = 'rapport_v2_conges_' . md5(json_encode($filters));
            
            return Cache::remember($cacheKey, 3600, function () use ($filters) {
                $query = Conge::with(['employe']);

                // Filtre par période
                if (isset($filters['date_debut'])) {
                    $query->where('date_debut', '>=', $filters['date_debut']);
                }
                if (isset($filters['date_fin'])) {
                    $query->where('date_fin', '<=', $filters['date_fin']);
                }

                // Filtre par département
                if (isset($filters['departement'])) {
                    $query->whereHas('employe', function ($q) use ($filters) {
                        $q->where('departement', $filters['departement']);
                    });
                }

                // Filtre par type
                if (isset($filters['type'])) {
                    $query->where('type', $filters['type']);
                }

                // Filtre par statut
                if (isset($filters['statut'])) {
                    $query->where('statut', $filters['statut']);
                }

                $conges = $query->get();

                // Statistiques
                $stats = [
                    'total' => $conges->count(),
                    'en_attente' => $conges->where('statut', 'en_attente')->count(),
                    'approuves' => $conges->where('statut', 'approuve')->count(),
                    'refuses' => $conges->where('statut', 'refuse')->count(),
                ];

                // Répartition par type
                $parType = $conges->groupBy('type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'jours' => $group->count(),
                    ];
                });

                // Répartition par département
                $parDepartement = $conges->groupBy(function ($conge) {
                    return $conge->employe->departement ?? 'Non assigné';
                })->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'jours' => $group->count(),
                    ];
                });

                return [
                    'periode' => [
                        'debut' => $filters['date_debut'] ?? 'Toutes dates',
                        'fin' => $filters['date_fin'] ?? 'Toutes dates',
                    ],
                    'statistiques' => $stats,
                    'par_type' => $parType,
                    'par_departement' => $parDepartement,
                    'conges' => $conges->values()->all(),
                    'filtres_appliques' => $filters,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du rapport congés: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Générer rapport des employés.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generateEmployesReport(array $filters = []): array
    {
        try {
            $cacheKey = 'rapport_v2_employes_' . md5(json_encode($filters));
            
            return Cache::remember($cacheKey, 3600, function () use ($filters) {
                $query = User::query();

                // Filtre par période d'embauche
                if (isset($filters['date_embauche_debut'])) {
                    $query->where('date_embauche', '>=', $filters['date_embauche_debut']);
                }
                if (isset($filters['date_embauche_fin'])) {
                    $query->where('date_embauche', '<=', $filters['date_embauche_fin']);
                }

                // Filtre par département
                if (isset($filters['departement'])) {
                    $query->where('departement', $filters['departement']);
                }

                // Filtre par statut
                if (isset($filters['is_active'])) {
                    $query->where('is_active', $filters['is_active']);
                }

                $employes = $query->get();

                // Embauches ce mois
                $embauchesMois = User::whereMonth('date_embauche', now()->month)
                    ->whereYear('date_embauche', now()->year)
                    ->count();

                // Départs ce mois (soft deleted)
                $departsMois = User::onlyTrashed()
                    ->whereMonth('deleted_at', now()->month)
                    ->whereYear('deleted_at', now()->year)
                    ->count();

                // Répartition par département
                $parDepartement = $employes->groupBy('departement')->map(function ($group) {
                    return $group->count();
                });

                // Contrats créés ce mois
                $contratsMois = Contrat::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();

                return [
                    'periode' => [
                        'debut' => $filters['date_embauche_debut'] ?? 'Toutes dates',
                        'fin' => $filters['date_embauche_fin'] ?? 'Toutes dates',
                    ],
                    'statistiques' => [
                        'total_employes' => $employes->count(),
                        'actifs' => $employes->where('is_active', true)->count(),
                        'embauches_mois' => $embauchesMois,
                        'departs_mois' => $departsMois,
                        'contrats_crees_mois' => $contratsMois,
                    ],
                    'par_departement' => $parDepartement,
                    'employes' => $employes->values()->all(),
                    'filtres_appliques' => $filters,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du rapport employés: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Générer rapport d'activité (admin seulement).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generateActivityReport(array $filters = []): array
    {
        try {
            $cacheKey = 'rapport_v2_activite_' . md5(json_encode($filters));
            
            return Cache::remember($cacheKey, 3600, function () use ($filters) {
                $query = ActivityLog::with(['user']);

                // Filtre par utilisateur
                if (isset($filters['user_id'])) {
                    $query->where('user_id', $filters['user_id']);
                }

                // Filtre par entité
                if (isset($filters['entity_name'])) {
                    $query->where('entity_name', $filters['entity_name']);
                }

                // Filtre par action
                if (isset($filters['action'])) {
                    $query->where('action', $filters['action']);
                }

                // Filtre par période
                if (isset($filters['date_from'])) {
                    $query->where('timestamp', '>=', $filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $query->where('timestamp', '<=', $filters['date_to']);
                }

                $activites = $query->orderBy('timestamp', 'desc')->get();

                // Répartition par entité
                $parEntite = $activites->groupBy('entity_name')->map(function ($group) {
                    return $group->count();
                });

                // Répartition par action
                $parAction = $activites->groupBy('action')->map(function ($group) {
                    return $group->count();
                });

                // Répartition par utilisateur
                $parUtilisateur = $activites->groupBy(function ($log) {
                    return $log->user ? $log->user->nom . ' ' . $log->user->prenom : 'Système';
                })->map(function ($group) {
                    return $group->count();
                });

                return [
                    'periode' => [
                        'debut' => $filters['date_from'] ?? 'Toutes dates',
                        'fin' => $filters['date_to'] ?? 'Toutes dates',
                    ],
                    'statistiques' => [
                        'total_actions' => $activites->count(),
                    ],
                    'par_entite' => $parEntite,
                    'par_action' => $parAction,
                    'par_utilisateur' => $parUtilisateur,
                    'activites' => $activites->values()->all(),
                    'filtres_appliques' => $filters,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du rapport activité: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Nettoyer le cache des rapports.
     */
    public function clearCache(): void
    {
        try {
            Cache::flush();
        } catch (\Exception $e) {
            Log::error('Erreur lors du nettoyage du cache: ' . $e->getMessage());
        }
    }
}

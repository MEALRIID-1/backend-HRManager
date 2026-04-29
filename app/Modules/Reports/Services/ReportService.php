<?php

namespace App\Modules\Reports\Services;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use App\Models\Validation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service de rapports avec requêtes SQL optimisées
 * Toutes les requêtes utilisent eager loading, selectRaw, withCount pour éviter N+1
 */
class ReportService
{
    private const CACHE_TTL = 300; // 5 minutes

    // ============================================================================
    // KPIs RH (Admin/RH)
    // ============================================================================

    /**
     * KPIs RH globaux - Requête optimisée avec sous-requêtes et agrégations
     * 
     * Requête SQL générée:
     * SELECT 
     *   (SELECT COUNT(*) FROM users WHERE est_actif = 1) as total_employes,
     *   (SELECT COUNT(*) FROM contrats WHERE etat = 'en_cours' AND date_fin <= DATE_ADD(NOW(), INTERVAL 30 DAY)) as contrats_expirant_30j,
     *   ...
     */
    public function getHrKpis(): array
    {
        return Cache::tags(['dashboard', 'hr_kpis'])->remember('hr_kpis', self::CACHE_TTL, function () {
            // Total employés actifs - requête simple indexée sur est_actif
            $totalEmployes = User::actif()->count();

            // Contrats expirant dans 30 jours - utilisation du scope expirantSous avec index sur date_fin
            $contratsExpirant = Contrat::actifs()
                ->expirantSous(30)
                ->with(['employe:id,name,email']) // eager loading minimal
                ->count();

            // Congés en attente par niveau - une seule requête avec CASE pour dénombrer
            // Plus efficace que 3 requêtes séparées
            $congesEnAttente = DB::selectOne("
                SELECT 
                    SUM(CASE WHEN etat = 'soumis' THEN 1 ELSE 0 END) as attente_manager,
                    SUM(CASE WHEN etat = 'valide_manager' THEN 1 ELSE 0 END) as attente_rh,
                    SUM(CASE WHEN etat = 'valide_rh' THEN 1 ELSE 0 END) as attente_directeur,
                    COUNT(*) as total
                FROM conges 
                WHERE etat IN ('soumis', 'valide_manager', 'valide_rh')
            ");

            // Taux d'absentéisme du mois en cours
            // Calcul: (jours de congés approuvés / jours ouvrables) / nombre d'employés
            $tauxAbsenteisme = $this->calculerTauxAbsenteisme();

            // Masse salariale du mois - somme des salaires des contrats actifs
            // Utilise selectRaw pour calcul direct en SQL
            $masseSalariale = Contrat::actifs()
                ->selectRaw('SUM(salaire) as total')
                ->value('total') ?? 0;

            // Nouveaux employés (30 derniers jours) - index sur date_embauche
            $nouveauxEmployes = User::actif()
                ->whereDate('date_embauche', '>=', now()->subDays(30))
                ->count();

            // Départs (contrats terminés récemment)
            $departs = Contrat::termines()
                ->whereDate('date_terminaison', '>=', now()->subDays(30))
                ->count();

            // Congés bloqués (>72h sans action)
            $congesBloques = Conge::whereIn('etat', ['soumis', 'valide_manager', 'valide_rh'])
                ->where('updated_at', '<', now()->subHours(72))
                ->count();

            // Totaux congés pour les KPIs secondaires
            $totalDemandes = Conge::count();
            $totalApprouves = Conge::where('etat', 'approuve')->count();
            $totalRefuses = Conge::where('etat', 'like', 'refuse%')->count();
            $totalContrats = Contrat::count();

            return [
                // KPIs principaux (camelCase pour le frontend)
                'totalEmployes' => $totalEmployes,
                'congesEnAttenteN3' => $congesEnAttente->attente_directeur ?? 0,
                'congesBloques' => $congesBloques,
                'masseSalariale' => round($masseSalariale, 2),

                // KPIs secondaires
                'totalDemandes' => $totalDemandes,
                'totalApprouves' => $totalApprouves,
                'totalRefuses' => $totalRefuses,
                'totalContrats' => $totalContrats,

                // Données détaillées
                'contrats_expirant_30j' => $contratsExpirant,
                'conges_en_attente' => [
                    'total' => $congesEnAttente->total ?? 0,
                    'par_niveau' => [
                        'manager' => $congesEnAttente->attente_manager ?? 0,
                        'rh' => $congesEnAttente->attente_rh ?? 0,
                        'directeur' => $congesEnAttente->attente_directeur ?? 0,
                    ],
                ],
                'taux_absenteisme_mois' => round($tauxAbsenteisme, 2),
                'masse_salariale_mois' => round($masseSalariale, 2),
                'nouveaux_employes_30j' => $nouveauxEmployes,
                'departs_30j' => $departs,
                'evolution_effectif' => $nouveauxEmployes - $departs,
            ];
        });
    }

    /**
     * Calcul du taux d'absentéisme avec requête optimisée
     * Utilise une sous-requête pour calculer les jours de congé en une seule passe
     */
    private function calculerTauxAbsenteisme(): float
    {
        $debutMois = now()->startOfMonth();
        $finMois = now()->endOfMonth();

        // Jours ouvrables du mois (hors weekends)
        $joursOuvrables = $this->calculerJoursOuvrables($debutMois, $finMois);

        // Nombre total d'employés actifs pendant la période
        $nbEmployes = User::actif()->count();

        if ($nbEmployes === 0 || $joursOuvrables === 0) {
            return 0;
        }

        // Total de jours de congés approuvés sur la période
        // Requête optimisée avec index sur etat et plage de dates
        $joursConges = Conge::approuves()
            ->where(function ($q) use ($debutMois, $finMois) {
                $q->whereBetween('date_debut', [$debutMois, $finMois])
                  ->orWhereBetween('date_fin', [$debutMois, $finMois])
                  ->orWhere(function ($sq) use ($debutMois, $finMois) {
                      $sq->where('date_debut', '<=', $debutMois)
                         ->where('date_fin', '>=', $finMois);
                  });
            })
            ->sum('nombre_jours');

        // Taux = (jours d'absence / (jours ouvrables * employés)) * 100
        return ($joursConges / ($joursOuvrables * $nbEmployes)) * 100;
    }

    /**
     * Calcule les jours ouvrables (lundi-vendredi) dans une période
     */
    private function calculerJoursOuvrables(Carbon $debut, Carbon $fin): int
    {
        $jours = 0;
        $current = $debut->copy();

        while ($current <= $fin) {
            if (!$current->isWeekend()) {
                $jours++;
            }
            $current->addDay();
        }

        return $jours;
    }

    // ============================================================================
    // Dashboard Manager
    // ============================================================================

    /**
     * Dashboard Manager - Vue de l'équipe
     * 
     * Requête optimisée avec with() pour charger les relations en une fois,
     * évite le N+1 sur les congés de chaque employé
     */
    public function getManagerDashboard(int $managerId): array
    {
        return Cache::tags(['dashboard', 'manager', "manager_{$managerId}"])->remember(
            "manager_dashboard_{$managerId}",
            self::CACHE_TTL,
            function () use ($managerId) {
                // Récupérer l'équipe avec eager loading des relations nécessaires
                $equipe = User::parManager($managerId)
                    ->actif()
                    ->with([
                        // Charger seulement les congés en cours et futurs
                        'conges' => function ($q) {
                            $q->whereDate('date_fin', '>=', now()->startOfDay())
                              ->whereNotIn('etat', [Conge::ETAT_REFUSE_MANAGER, Conge::ETAT_REFUSE_RH, Conge::ETAT_REFUSE_DIRECTEUR, Conge::ETAT_ANNULE])
                              ->orderBy('date_debut');
                        },
                        // Charger le contrat actif avec seulement les champs nécessaires
                        'contrats' => function ($q) {
                            $q->actifs()
                              ->select(['id', 'employe_id', 'date_debut', 'date_fin', 'est_en_periode_essai', 'duree_periode_essai_jours']);
                        },
                    ])
                    ->withCount([
                        // Compteurs optimisés - calculés en SQL pas en PHP
                        'conges as conges_en_cours_count' => function ($q) {
                            $q->whereDate('date_debut', '<=', now())
                              ->whereDate('date_fin', '>=', now())
                              ->approuves();
                        },
                    ])
                    ->get(['id', 'name', 'email', 'photo', 'date_embauche']);

                // Statistiques de l'équipe
                $totalEquipe = $equipe->count();
                $presentAujourdhui = $this->calculerPresentsAujourdhui($equipe);
                $absentsAujourdhui = $totalEquipe - $presentAujourdhui;

                // Congés en attente de validation par ce manager
                $congesAValider = Conge::aPrevaliderParManager($managerId)
                    ->with(['employe:id,name,email'])
                    ->get(['id', 'employe_id', 'type', 'date_debut', 'date_fin', 'nombre_jours', 'raison']);

                // Périodes d'essai en cours
                $periodesEssai = Contrat::actifs()
                    ->enPeriodeEssai()
                    ->whereHas('employe', function ($q) use ($managerId) {
                        $q->where('manager_id', $managerId);
                    })
                    ->with(['employe:id,name,email,date_embauche'])
                    ->get(['id', 'employe_id', 'date_debut', 'duree_periode_essai_jours'])
                    ->map(function ($contrat) {
                        return [
                            'employe_id' => $contrat->employe->id,
                            'employe_nom' => $contrat->employe->name,
                            'date_debut_periode' => $contrat->date_debut->format('Y-m-d'),
                            'jours_restant_essai' => max(0, $contrat->duree_periode_essai_jours - $contrat->date_debut->diffInDays(now())),
                            'progression' => $contrat->progression_periode_essai,
                        ];
                    });

                return [
                    'equipe' => [
                        'total' => $totalEquipe,
                        'membres' => $equipe->map(function ($employe) {
                            return [
                                'id' => $employe->id,
                                'nom' => $employe->name,
                                'email' => $employe->email,
                                'photo_url' => $employe->photo_url,
                                'en_conge' => $employe->conges_en_cours_count > 0,
                                'est_present' => $employe->conges_en_cours_count === 0,
                            ];
                        }),
                    ],
                    'presence_aujourdhui' => [
                        'presents' => $presentAujourdhui,
                        'absents' => $absentsAujourdhui,
                        'taux_presence' => $totalEquipe > 0 ? round(($presentAujourdhui / $totalEquipe) * 100, 2) : 0,
                    ],
                    'conges_a_valider' => [
                        'total' => $congesAValider->count(),
                        'demandes' => $congesAValider,
                    ],
                    'periodes_essai' => [
                        'total' => $periodesEssai->count(),
                        'details' => $periodesEssai,
                    ],
                ];
            }
        );
    }

    /**
     * Calcule les présents aujourd'hui en vérifiant les congés
     * Optimisé pour ne pas faire de requêtes N+1
     */
    private function calculerPresentsAujourdhui(Collection $equipe): int
    {
        $aujourdhui = now()->format('Y-m-d');

        return $equipe->filter(function ($employe) use ($aujourdhui) {
            // Vérifier si l'employé a un congé approuvé aujourd'hui
            $enConge = $employe->conges->contains(function ($conge) use ($aujourdhui) {
                return $conge->estApprouve() &&
                       $conge->date_debut->format('Y-m-d') <= $aujourdhui &&
                       $conge->date_fin->format('Y-m-d') >= $aujourdhui;
            });

            return !$enConge;
        })->count();
    }

    // ============================================================================
    // Dashboard Employé
    // ============================================================================

    /**
     * Dashboard Employé - Vue personnelle
     * 
     * Toutes les données sont récupérées en une requête ou avec eager loading
     */
    public function getEmployeeDashboard(int $employeId): array
    {
        return Cache::tags(['dashboard', 'employee', "employee_{$employeId}"])->remember(
            "employee_dashboard_{$employeId}",
            self::CACHE_TTL,
            function () use ($employeId) {
                // Récupérer l'employé avec toutes ses relations en une requête
                $employe = User::with([
                    // Dernier contrat actif
                    'contrats' => function ($q) {
                        $q->actifs()
                          ->latest('date_debut')
                          ->limit(1)
                          ->select(['id', 'employe_id', 'type', 'date_debut', 'date_fin', 'salaire', 'est_en_periode_essai']);
                    },
                    // Dernière fiche de paie
                    'fichePaies' => function ($q) {
                        $q->latest('date_paie')
                          ->limit(1)
                          ->select(['id', 'employe_id', 'salaire_net', 'date_paie']);
                    },
                    // Congés à venir
                    'conges' => function ($q) {
                        $q->whereDate('date_fin', '>=', now())
                          ->whereNotIn('etat', [Conge::ETAT_ANNULE, Conge::ETAT_REFUSE_MANAGER, Conge::ETAT_REFUSE_RH, Conge::ETAT_REFUSE_DIRECTEUR])
                          ->orderBy('date_debut')
                          ->limit(5)
                          ->select(['id', 'employe_id', 'type', 'date_debut', 'date_fin', 'nombre_jours', 'etat']);
                    },
                ])->findOrFail($employeId, ['id', 'name', 'email', 'photo', 'date_embauche']);

                // Calcul des soldes - requêtes optimisées avec sum() direct en SQL
                $soldeCongePaye = $this->calculerSolde($employeId, Conge::TYPE_CONGE_PAYE);
                $soldeRTT = $this->calculerSolde($employeId, Conge::TYPE_RTT);

                $contrat = $employe->contrats->first();
                $fichePaie = $employe->fichePaies->first();

                return [
                    'employe' => [
                        'id' => $employe->id,
                        'nom' => $employe->name,
                        'photo_url' => $employe->photo_url,
                        'anciennete' => $employe->anciennete_formatee,
                    ],
                    'soldes_conges' => [
                        'conge_paye' => $soldeCongePaye,
                        'rtt' => $soldeRTT,
                    ],
                    'contrat_actuel' => $contrat ? [
                        'id' => $contrat->id,
                        'type' => $contrat->type,
                        'date_debut' => $contrat->date_debut->format('Y-m-d'),
                        'date_fin' => $contrat->date_fin?->format('Y-m-d'),
                        'salaire_brut' => $contrat->salaire,
                        'est_en_periode_essai' => $contrat->est_en_periode_essai,
                    ] : null,
                    'prochaine_paie' => $fichePaie ? [
                        'date' => $fichePaie->date_paie->format('Y-m-d'),
                        'salaire_net' => $fichePaie->salaire_net,
                    ] : $this->calculerProchainePaie($employeId),
                    'conges_a_venir' => $employe->conges->map(function ($conge) {
                        return [
                            'id' => $conge->id,
                            'type' => $conge->type,
                            'date_debut' => $conge->date_debut->format('Y-m-d'),
                            'date_fin' => $conge->date_fin->format('Y-m-d'),
                            'nombre_jours' => $conge->nombre_jours,
                            'etat' => $conge->etat,
                            'etat_label' => $conge->etat_label,
                        ];
                    }),
                    'notifications' => $this->getNotificationsNonLues($employeId),
                ];
            }
        );
    }

    /**
     * Calcule le solde d'un type de congé avec requête optimisée
     * Utilise une seule requête avec sum() et sous-requête
     */
    private function calculerSolde(int $employeId, string $type): array
    {
        $soldeAnnuel = match ($type) {
            Conge::TYPE_CONGE_PAYE => 25,
            Conge::TYPE_RTT => 10,
            default => 0,
        };

        // Requête optimisée: SUM direct en SQL avec index sur employe_id + type + etat
        $joursUtilises = Conge::parEmploye($employeId)
            ->where('type', $type)
            ->whereIn('etat', [Conge::ETAT_APPROUVE, Conge::ETAT_VALIDE_MANAGER, Conge::ETAT_VALIDE_RH])
            ->whereYear('date_debut', now()->year)
            ->sum('nombre_jours');

        return [
            'total_annuel' => $soldeAnnuel,
            'jours_utilises' => (int) $joursUtilises,
            'jours_restants' => max(0, $soldeAnnuel - $joursUtilises),
        ];
    }

    /**
     * Calcule la prochaine date de paie (généralement dernier jour du mois)
     */
    private function calculerProchainePaie(int $employeId): ?array
    {
        // Si pas de fiche de paie précédente, calculer le prochain jour de paie
        $prochaineDate = now()->endOfMonth();
        
        if ($prochaineDate < now()) {
            $prochaineDate = now()->addMonth()->endOfMonth();
        }

        return [
            'date' => $prochaineDate->format('Y-m-d'),
            'salaire_net' => null, // Inconnu avant calcul
        ];
    }

    /**
     * Récupère les notifications non lues avec limite
     */
    private function getNotificationsNonLues(int $employeId): array
    {
        return DB::table('notifications')
            ->where('notifiable_id', $employeId)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'type', 'data', 'created_at'])
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'data' => json_decode($notif->data, true),
                    'date' => $notif->created_at,
                ];
            })
            ->toArray();
    }

    // ============================================================================
    // Rapports pour Export
    // ============================================================================

    /**
     * Données pour export des congés - avec filtres
     * 
     * Requête optimisée avec joins pour éviter les N+1 sur les relations
     */
    public function getLeavesForExport(array $filters = []): Collection
    {
        $query = DB::table('conges as c')
            ->join('users as u', 'c.employe_id', '=', 'u.id')
            ->leftJoin('users as m', 'u.manager_id', '=', 'm.id')
            ->select([
                'c.id',
                'u.name as employe_nom',
                'u.email as employe_email',
                'm.name as manager_nom',
                'c.type',
                'c.date_debut',
                'c.date_fin',
                'c.nombre_jours',
                'c.etat',
                'c.raison',
                'c.created_at',
            ]);

        // Filtres
        if (!empty($filters['etat'])) {
            $query->where('c.etat', $filters['etat']);
        }

        if (!empty($filters['type'])) {
            $query->where('c.type', $filters['type']);
        }

        if (!empty($filters['employe_id'])) {
            $query->where('c.employe_id', $filters['employe_id']);
        }

        if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('c.date_debut', [$filters['date_debut'], $filters['date_fin']])
                  ->orWhereBetween('c.date_fin', [$filters['date_debut'], $filters['date_fin']]);
            });
        }

        if (!empty($filters['manager_id'])) {
            $query->where('u.manager_id', $filters['manager_id']);
        }

        return $query->orderByDesc('c.created_at')->get();
    }

    /**
     * Données pour export des employés avec stats
     * Utilise withCount pour les agrégations
     */
    public function getEmployeesForExport(array $filters = []): Collection
    {
        $query = User::with(['contrats' => function ($q) {
                $q->actifs()->select(['id', 'employe_id', 'type', 'salaire', 'date_debut']);
            }])
            ->withCount([
                'conges as total_conges_annee' => function ($q) {
                    $q->approuves()->whereYear('date_debut', now()->year);
                },
                'conges as jours_conges_pris' => function ($q) {
                    $q->approuves()
                      ->whereYear('date_debut', now()->year)
                      ->selectRaw('SUM(nombre_jours)');
                },
            ])
            ->select([
                'id',
                'name',
                'email',
                'telephone',
                'date_embauche',
                'est_actif',
                'departement_id',
                'manager_id',
            ]);

        if (!empty($filters['actif'])) {
            $query->where('est_actif', $filters['actif']);
        }

        if (!empty($filters['departement_id'])) {
            $query->where('departement_id', $filters['departement_id']);
        }

        if (!empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        return $query->get()->map(function ($employe) {
            $contrat = $employe->contrats->first();
            
            return [
                'id' => $employe->id,
                'nom' => $employe->name,
                'email' => $employe->email,
                'telephone' => $employe->telephone,
                'date_embauche' => $employe->date_embauche?->format('Y-m-d'),
                'statut' => $employe->est_actif ? 'Actif' : 'Inactif',
                'type_contrat' => $contrat?->type,
                'salaire_brut' => $contrat?->salaire,
                'total_conges_pris' => $employe->total_conges_annee,
                'jours_conges_pris' => $employe->jours_conges_pris ?? 0,
            ];
        });
    }

    /**
     * Invalide le cache du dashboard
     */
    public function invalidateCache(string $type, ?int $id = null): void
    {
        if ($id) {
            Cache::tags([$type, "{$type}_{$id}"])->flush();
        } else {
            Cache::tags([$type])->flush();
        }
    }
}

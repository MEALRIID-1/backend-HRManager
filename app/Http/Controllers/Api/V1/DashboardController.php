<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\CongeResource;
use App\Http\Resources\NotificationResource;
use App\Models\ActivityLog;
use App\Models\Conge;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use App\Services\CongeService;
use App\Services\RapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RapportService $rapportService,
        private readonly CongeService $congeService,
    ) {
    }

    /**
     * Admin dashboard : stats globales.
     */
    public function adminDashboard(): JsonResponse
    {
        try {
            // Stats principales
            $totalEmployes = User::where('is_active', true)->count();
            $contratsActifs = Contrat::where('statut', 'actif')->count();
            $congesEnAttente = Conge::where('statut', 'en_attente')->count();
            $congesApprouvesMois = Conge::where('statut', 'approuve')
                ->whereMonth('date_debut', now()->month)
                ->whereYear('date_debut', now()->year)
                ->count();

            // Variations par rapport au mois dernier
            $totalEmployesMoisDernier = User::where('is_active', true)
                ->whereDate('created_at', '<', now()->startOfMonth())
                ->count();
            $employesVariation = $totalEmployesMoisDernier > 0
                ? round((($totalEmployes - $totalEmployesMoisDernier) / $totalEmployesMoisDernier) * 100, 1)
                : 0;

            $contratsActifsMoisDernier = Contrat::where('statut', 'actif')
                ->whereDate('created_at', '<', now()->startOfMonth())
                ->count();
            $contratsVariation = $contratsActifsMoisDernier > 0
                ? round((($contratsActifs - $contratsActifsMoisDernier) / $contratsActifsMoisDernier) * 100, 1)
                : 0;

            $congesAttenteMoisDernier = Conge::where('statut', 'en_attente')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();
            $congesAttenteVariation = $congesAttenteMoisDernier > 0
                ? round((($congesEnAttente - $congesAttenteMoisDernier) / $congesAttenteMoisDernier) * 100, 1)
                : 0;

            $congesApprouvesMoisDernier = Conge::where('statut', 'approuve')
                ->whereMonth('date_debut', now()->subMonth()->month)
                ->whereYear('date_debut', now()->subMonth()->year)
                ->count();
            $congesApprouvesVariation = $congesApprouvesMoisDernier > 0
                ? round((($congesApprouvesMois - $congesApprouvesMoisDernier) / $congesApprouvesMoisDernier) * 100, 1)
                : 0;

            // Congés par mois (6 derniers mois)
            $congesParMois = Conge::where('statut', 'approuve')
                ->where('date_debut', '>=', now()->subMonths(6)->startOfMonth())
                ->selectRaw("DATE_FORMAT(date_debut, '%Y-%m') as mois_key, DATE_FORMAT(date_debut, '%b') as mois, COUNT(*) as nombre")
                ->groupBy('mois_key', 'mois')
                ->orderBy('mois_key', 'asc')
                ->get()
                ->map(fn($item) => [
                    'mois'   => $this->traduireMois($item->mois),
                    'nombre' => $item->nombre,
                ]);

            // Répartition par département
            $repartitionDepartement = User::where('is_active', true)
                ->whereNotNull('departement')
                ->selectRaw('departement, count(*) as total')
                ->groupBy('departement')
                ->get();

            // Contrats expirant dans les 30 jours avec détails
            $contratsExpirants = Contrat::where('date_fin', '<=', now()->addDays(30))
                ->where('date_fin', '>=', now())
                ->where('statut', 'actif')
                ->with('employe')
                ->get()
                ->map(fn($contrat) => [
                    'id'              => $contrat->id,
                    'employe_nom'     => $contrat->employe->nom ?? '',
                    'employe_prenom'  => $contrat->employe->prenom ?? '',
                    'date_fin'        => $contrat->date_fin,
                    'jours_restants'  => (int) now()->diffInDays($contrat->date_fin),
                ]);

            // Activité récente
            $activiteRecente = ActivityLog::with('user')
                ->orderBy('timestamp', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistiques' => [
                        'total_employes'              => $totalEmployes,
                        'employes_variation'          => $employesVariation,
                        'contrats_actifs'             => $contratsActifs,
                        'contrats_variation'          => $contratsVariation,
                        'conges_en_attente'           => $congesEnAttente,
                        'conges_attente_variation'    => $congesAttenteVariation,
                        'conges_approuves_mois'       => $congesApprouvesMois,
                        'conges_approuves_variation'  => $congesApprouvesVariation,
                    ],
                    'alertes' => [
                        'contrats_expirant'          => $contratsExpirants->count(),
                        'contrats_expirant_details'  => $contratsExpirants,
                    ],
                    'conges_par_mois'         => $congesParMois,
                    'activite_recente'        => ActivityLogResource::collection($activiteRecente),
                    'repartition_departement' => $repartitionDepartement,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur dashboard admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Traduit les abréviations de mois en français.
     */
    private function traduireMois(string $mois): string
    {
        $traductions = [
            'Jan' => 'Jan', 'Feb' => 'Fév', 'Mar' => 'Mar',
            'Apr' => 'Avr', 'May' => 'Mai', 'Jun' => 'Juin',
            'Jul' => 'Juil', 'Aug' => 'Aoû', 'Sep' => 'Sep',
            'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Déc',
        ];
        return $traductions[$mois] ?? $mois;
    }

    /**
     * RH dashboard : stats RH.
     */
    public function rhDashboard(): JsonResponse
    {
        try {
            $employesActifs = User::where('is_active', true)->count();
            $nouvellesEmbauches = User::whereMonth('date_embauche', now()->month)
                ->whereYear('date_embauche', now()->year)
                ->count();
            $congesAValider = Conge::where('statut', 'en_attente')->count();
            $contratsCrees = Contrat::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $contratsExpirant = Contrat::where('date_fin', '<=', now()->addDays(30))
                ->where('date_fin', '>=', now())
                ->where('statut', 'actif')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistiques' => [
                        'employes_actifs'      => $employesActifs,
                        'nouvelles_embauches'  => $nouvellesEmbauches,
                        'contrats_crees_mois'  => $contratsCrees,
                    ],
                    'alertes' => [
                        'conges_a_valider'   => $congesAValider,
                        'contrats_expirant'  => $contratsExpirant,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur dashboard RH: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Manager dashboard : stats équipe (département).
     */
    public function managerDashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $departement = $user->departement;

            $employesDepartement = User::where('departement', $departement)
                ->where('is_active', true)
                ->get();
            $employesIds = $employesDepartement->pluck('id');

            $congesEquipe = Conge::whereIn('user_id', $employesIds)
                ->where('statut', 'approuve')
                ->where('date_debut', '>=', now())
                ->with('employe')
                ->get();

            $congesAValider = Conge::whereIn('user_id', $employesIds)
                ->where('statut', 'en_attente')
                ->with('employe')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'equipe' => [
                        'departement' => $departement,
                        'effectif'    => $employesDepartement->count(),
                    ],
                    'conges_equipe'        => CongeResource::collection($congesEquipe),
                    'conges_a_valider_n1'  => CongeResource::collection($congesAValider),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur dashboard manager: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Employé dashboard : stats personnelles.
     */
    public function employeDashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $soldeConges = $this->congeService->getSoldeConges($user->id);
            $contratActif = Contrat::where('user_id', $user->id)
                ->where('statut', 'actif')
                ->with('employe')
                ->first();

            $prochainsConges = Conge::where('user_id', $user->id)
                ->where('date_debut', '>=', now())
                ->whereIn('statut', ['approuve', 'partiellement_valide'])
                ->orderBy('date_debut', 'asc')
                ->limit(3)
                ->get();

            $notificationsNonLues = Notification::where('user_id', $user->id)
                ->where('lu', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'solde_conges'  => $soldeConges,
                    'contrat_actif' => $contratActif ? [
                        'id'           => $contratActif->id,
                        'type'         => $contratActif->type,
                        'date_debut'   => $contratActif->date_debut,
                        'date_fin'     => $contratActif->date_fin,
                        'salaire_base' => $contratActif->salaire_base,
                    ] : null,
                    'prochains_conges'        => CongeResource::collection($prochainsConges),
                    'notifications_non_lues'  => $notificationsNonLues,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur dashboard employé: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Main dashboard basé sur le rôle.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->roles->contains('nom', 'admin')) {
            return $this->adminDashboard();
        }

        if ($user->roles->contains('nom', 'RH')) {
            return $this->rhDashboard();
        }

        if ($user->roles->contains('nom', 'manager')) {
            return $this->managerDashboard($request);
        }

        return $this->employeDashboard($request);
    }
}
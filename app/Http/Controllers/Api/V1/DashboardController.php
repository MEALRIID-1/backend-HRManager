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
            $totalEmployes = User::where('is_active', true)->count();
            $contratsActifs = Contrat::where('etat', 'actif')->count();
            $congesEnAttente = Conge::where('etat', 'en_attente')->count();
            $congesApprouvesMois = Conge::where('etat', 'approuve')
                ->whereMonth('date_debut', now()->month)
                ->whereYear('date_debut', now()->year)
                ->count();
            $contratsExpirant = Contrat::where('date_fin', '<=', now()->addDays(30))
                ->where('date_fin', '>=', now())
                ->where('etat', 'actif')
                ->count();
            $activiteRecente = ActivityLog::with('user')
                ->orderBy('timestamp', 'desc')
                ->limit(10)
                ->get();
            $repartitionDepartement = User::where('is_active', true)
                ->whereNotNull('departement')
                ->selectRaw('departement, count(*) as total')
                ->groupBy('departement')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistiques' => [
                        'total_employes' => $totalEmployes,
                        'contrats_actifs' => $contratsActifs,
                        'conges_en_attente' => $congesEnAttente,
                        'conges_approuves_mois' => $congesApprouvesMois,
                    ],
                    'alertes' => [
                        'contrats_expirant' => $contratsExpirant,
                    ],
                    'activite_recente' => ActivityLogResource::collection($activiteRecente),
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
     * RH dashboard : stats RH.
     */
    public function rhDashboard(): JsonResponse
    {
        try {
            $employesActifs = User::where('is_active', true)->count();
            $nouvellesEmbauches = User::whereMonth('date_embauche', now()->month)
                ->whereYear('date_embauche', now()->year)
                ->count();
            $congesAValider = Conge::where('etat', 'en_attente')->count();
            $contratsCrees = Contrat::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $contratsExpirant = Contrat::where('date_fin', '<=', now()->addDays(30))
                ->where('date_fin', '>=', now())
                ->where('etat', 'actif')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistiques' => [
                        'employes_actifs' => $employesActifs,
                        'nouvelles_embauches' => $nouvellesEmbauches,
                        'contrats_crees_mois' => $contratsCrees,
                    ],
                    'alertes' => [
                        'conges_a_valider' => $congesAValider,
                        'contrats_expirant' => $contratsExpirant,
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

            // Employés du même département
            $employesDepartement = User::where('departement', $departement)
                ->where('is_active', true)
                ->get();
            $employesIds = $employesDepartement->pluck('id');

            $congesEquipe = Conge::whereIn('user_id', $employesIds)
                ->where('etat', 'approuve')
                ->where('date_debut', '>=', now())
                ->with('employe')
                ->get();

            $congesAValider = Conge::whereIn('user_id', $employesIds)
                ->where('etat', 'en_attente')
                ->with('employe')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'equipe' => [
                        'departement' => $departement,
                        'effectif' => $employesDepartement->count(),
                    ],
                    'conges_equipe' => CongeResource::collection($congesEquipe),
                    'conges_a_valider_n1' => CongeResource::collection($congesAValider),
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
                ->where('etat', 'actif')
                ->with('employe')
                ->first();

            $prochainsConges = Conge::where('user_id', $user->id)
                ->where('date_debut', '>=', now())
                ->whereIn('etat', ['approuve', 'partiellement_valide'])
                ->orderBy('date_debut', 'asc')
                ->limit(3)
                ->get();

            $notificationsNonLues = Notification::where('user_id', $user->id)
                ->where('statut', 'non_lue')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'solde_conges' => $soldeConges,
                    'contrat_actif' => $contratActif ? [
                        'id' => $contratActif->id,
                        'type' => $contratActif->type,
                        'date_debut' => $contratActif->date_debut,
                        'date_fin' => $contratActif->date_fin,
                        'salaire_base' => $contratActif->salaire_base,
                    ] : null,
                    'prochains_conges' => CongeResource::collection($prochainsConges),
                    'notifications_non_lues' => $notificationsNonLues,
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

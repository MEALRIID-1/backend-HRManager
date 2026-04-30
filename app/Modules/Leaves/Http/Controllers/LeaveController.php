<?php

namespace App\Modules\Leaves\Http\Controllers;

use App\Models\Conge;
use App\Models\User;
use App\Modules\Leaves\Http\Requests\CancelLeaveRequest;
use App\Modules\Leaves\Http\Requests\RejectLeaveRequest;
use App\Modules\Leaves\Http\Requests\StoreLeaveRequest;
use App\Modules\Leaves\Http\Resources\LeaveCollection;
use App\Modules\Leaves\Http\Resources\LeaveResource;
use App\Modules\Leaves\Services\LeaveWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController
{
    private LeaveWorkflowService $workflowService;

    public function __construct(LeaveWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Liste des congés selon le rôle de l'utilisateur.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Conge::with(['employe', 'validations.validateur']);

            // Filtrer selon le rôle
            if ($user->hasRole(['directeur', 'admin'])) {
                // Directeur/Admin : voit tout
                // Pas de filtre supplémentaire
            } elseif ($user->hasRole('rh')) {
                // RH : voit ceux à valider par RH + son équipe si manager
                if ($user->hasRole('manager')) {
                    $query->where(function ($q) use ($user) {
                        $q->where('etat', Conge::ETAT_VALIDE_MANAGER)
                          ->orWhereHas('employe', function ($sq) use ($user) {
                              $sq->where('manager_id', $user->id);
                          });
                    });
                } else {
                    $query->valideRH();
                }
            } elseif ($user->hasRole('manager')) {
                // Manager : voit son équipe en attente + ses propres congés
                $query->where(function ($q) use ($user) {
                    $q->where('etat', Conge::ETAT_SOUMIS)
                      ->whereHas('employe', function ($sq) use ($user) {
                          $sq->where('manager_id', $user->id);
                      })
                      ->orWhere('employe_id', $user->id);
                });
            } else {
                // Employé : ne voit que ses propres congés
                $query->parEmploye($user->id);
            }

            // Filtres optionnels
            if ($request->has('etat')) {
                $query->where('etat', $request->input('etat'));
            }

            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->parPeriode(
                    Carbon::parse($request->input('date_debut')),
                    Carbon::parse($request->input('date_fin'))
                );
            }

            $perPage = $request->input('per_page', 15);
            $conges = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Congés récupérés avec succès.',
                'data' => new LeaveCollection($conges),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des congés.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Créer un congé (en brouillon).
     */
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $employe = auth()->user();

            // Vérifier le chevauchement
            if ($this->workflowService->verifierChevauchement(
                $employe->id,
                Carbon::parse($data['date_debut']),
                Carbon::parse($data['date_fin'])
            )) {
                throw new \Exception('Un congé existe déjà sur cette période.');
            }

            // Vérifier le solde si type comptabilisé
            $joursDemandes = Carbon::parse($data['date_debut'])->diffInDays(Carbon::parse($data['date_fin'])) + 1;
            if (!$this->workflowService->verifierSolde($employe, $data['type'], $joursDemandes)) {
                throw new \Exception('Solde insuffisant pour ce type de congé.');
            }

            $conge = Conge::create([
                'employe_id' => $employe->id,
                'type' => $data['type'],
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'],
                'raison' => $data['raison'] ?? null,
                'etat' => Conge::ETAT_BROUILLON,
                'commentaire' => $data['commentaire'] ?? null,
                'nombre_jours' => $joursDemandes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Congé créé en brouillon. Soumettez-le pour validation.',
                'data' => new LeaveResource($conge),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Afficher un congé.
     */
    public function show(Conge $conge): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier les permissions
            $canView = $user->id === $conge->employe_id ||
                       $user->hasRole(['rh', 'admin', 'directeur']) ||
                       ($user->hasRole('manager') && $conge->employe->manager_id === $user->id);

            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                    'data' => null,
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Congé récupéré avec succès.',
                'data' => new LeaveResource($conge->load(['employe', 'validations.validateur'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Soumettre un congé (brouillon -> soumis).
     */
    public function submit(Conge $conge): JsonResponse
    {
        try {
            $user = auth()->user();
            $conge = $this->workflowService->soumettre($conge, $user);

            return response()->json([
                'success' => true,
                'message' => 'Congé soumis pour validation.',
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Approuver un congé.
     */
    public function approve(Conge $conge): JsonResponse
    {
        try {
            $validateur = auth()->user();
            $conge = $this->workflowService->approuver($conge, $validateur, request('commentaire'));

            return response()->json([
                'success' => true,
                'message' => 'Congé approuvé.',
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Refuser un congé.
     */
    public function reject(RejectLeaveRequest $request, Conge $conge): JsonResponse
    {
        try {
            $validateur = auth()->user();
            $motif = $request->validated()['motif'];
            $conge = $this->workflowService->refuser($conge, $validateur, $motif);

            return response()->json([
                'success' => true,
                'message' => 'Congé refusé.',
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Super-validation (Directeur/Admin uniquement) — court-circuiter le workflow.
     */
    public function superValider(Request $request, Conge $conge): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole(['directeur', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux directeurs et administrateurs.',
                    'data' => null,
                ], 403);
            }

            $request->validate([
                'decision'    => 'required|in:approuve,refuse',
                'commentaire' => 'required|string|min:3',
                'motif_refus' => 'nullable|string',
            ]);

            $conge = $this->workflowService->superValider(
                $conge,
                $user,
                $request->input('decision'),
                $request->input('commentaire'),
                $request->input('motif_refus'),
            );

            $message = $request->input('decision') === 'approuve'
                ? 'Congé approuvé (super-validation).'
                : 'Congé refusé (super-validation).';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Annuler un congé.
     */
    public function cancel(CancelLeaveRequest $request, Conge $conge): JsonResponse
    {
        try {
            $user = auth()->user();
            $motif = $request->validated()['motif'];
            $conge = $this->workflowService->annuler($conge, $user, $motif);

            return response()->json([
                'success' => true,
                'message' => 'Congé annulé.',
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Obtenir le solde de congés.
     */
    public function getBalance(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $types = [
                Conge::TYPE_CONGE_PAYE,
                Conge::TYPE_RTT,
                Conge::TYPE_CONGE_SANS_SOLDE,
            ];

            $balances = [];
            foreach ($types as $type) {
                $balances[] = $this->workflowService->calculerSolde($user, $type, $request->input('annee'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Soldes récupérés.',
                'data' => $balances,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des soldes.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Congés en attente pour le dashboard.
     */
    public function pending(): JsonResponse
    {
        try {
            $user = auth()->user();
            $conges = collect();

            if ($user->hasRole('directeur') || $user->hasRole('admin')) {
                $conges = Conge::valideRH()->with('employe')->get();
            } elseif ($user->hasRole('rh')) {
                $conges = Conge::valideManager()->with('employe')->get();
            } elseif ($user->hasRole('manager')) {
                $conges = Conge::soumis()
                    ->whereHas('employe', function ($q) use ($user) {
                        $q->where('manager_id', $user->id);
                    })
                    ->with('employe')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Congés en attente.',
                'data' => LeaveResource::collection($conges),
                'total' => $conges->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Liste des congés supprimés (corbeille).
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Conge::onlyTrashed()->with(['employe']);

            // Filtrer selon le rôle
            if (!$user->hasRole(['directeur', 'admin', 'rh'])) {
                // Manager : ne voit que les congés de son équipe
                if ($user->hasRole('manager')) {
                    $query->whereHas('employe', function ($q) use ($user) {
                        $q->where('manager_id', $user->id);
                    });
                } else {
                    // Employé : ne voit que ses propres congés
                    $query->where('employe_id', $user->id);
                }
            }

            $conges = $query->orderBy('deleted_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Congés archivés récupérés avec succès.',
                'data' => LeaveResource::collection($conges),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des congés archivés : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Restaurer un congé supprimé.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $conge = Conge::onlyTrashed()->findOrFail($id);

            // Vérifier les permissions selon le rôle
            $canRestore = false;
            if ($user->hasRole(['directeur', 'admin', 'rh'])) {
                $canRestore = true;
            } elseif ($user->hasRole('manager')) {
                // Manager peut restaurer les congés de son équipe
                $canRestore = $conge->employe && $conge->employe->manager_id === $user->id;
            } else {
                // Employé peut restaurer ses propres congés
                $canRestore = $conge->employe_id === $user->id;
            }

            if (!$canRestore) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission de restaurer ce congé.',
                    'data' => null,
                ], 403);
            }

            $conge->restore();

            return response()->json([
                'success' => true,
                'message' => 'Congé restauré avec succès.',
                'data' => new LeaveResource($conge),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

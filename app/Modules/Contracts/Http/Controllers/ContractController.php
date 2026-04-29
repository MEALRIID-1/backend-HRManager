<?php

namespace App\Modules\Contracts\Http\Controllers;

use App\Models\Contrat;
use App\Modules\Contracts\Http\Requests\StoreContractRequest;
use App\Modules\Contracts\Http\Requests\TerminateContractRequest;
use App\Modules\Contracts\Http\Requests\UpdateContractRequest;
use App\Modules\Contracts\Http\Resources\ContractCollection;
use App\Modules\Contracts\Http\Resources\ContractResource;
use App\Modules\Contracts\Services\ContractService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractController
{
    private ContractService $service;

    public function __construct(ContractService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'etat',
                'type',
                'employe_id',
                'expirant_sous',
                'en_periode_essai',
                'sort_by',
                'sort_order',
            ]);

            // Alertes pour contrats expirant dans 30 jours
            $alertes = [];
            if ($request->boolean('avec_alertes')) {
                $expirants = $this->service->getExpiringContracts(30);
                $alertes = $expirants->map(function ($contrat) {
                    return [
                        'contrat_id' => $contrat->id,
                        'employe' => $contrat->employe->name,
                        'jours_restants' => $contrat->duree_restante,
                        'date_fin' => $contrat->date_fin?->format('Y-m-d'),
                    ];
                });
            }

            $perPage = $request->input('per_page', 15);
            $contracts = $this->service->getContracts($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Contrats récupérés avec succès.',
                'data' => new ContractCollection($contracts),
                'alertes' => $alertes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des contrats.',
                'data' => null,
            ], 500);
        }
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $contrat = $this->service->createContract($data, auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrat créé avec succès.',
                'data' => new ContractResource($contrat),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function show(Contrat $contract): JsonResponse
    {
        try {
            $contrat = $this->service->getContract($contract->id);

            if (!$contrat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrat non trouvé.',
                    'data' => null,
                ], 404);
            }

            // Vérifier les permissions
            if (!auth()->user()->can('view contracts') && 
                auth()->id() !== $contrat->employe_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                    'data' => null,
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contrat récupéré avec succès.',
                'data' => new ContractResource($contrat),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération.',
                'data' => null,
            ], 500);
        }
    }

    public function update(UpdateContractRequest $request, Contrat $contract): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $contrat = $this->service->updateContract($contract, $data, auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrat mis à jour avec succès.',
                'data' => new ContractResource($contrat),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function terminate(TerminateContractRequest $request, Contrat $contract): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $contrat = $this->service->terminateContract(
                $contract,
                $data['motif'],
                !empty($data['date']) ? Carbon::parse($data['date']) : null,
                auth()->id()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrat terminé avec succès.',
                'data' => new ContractResource($contrat),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la terminaison : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function destroy(Contrat $contract): JsonResponse
    {
        try {
            if (!auth()->user()->can('delete-contracts')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                    'data' => null,
                ], 403);
            }

            $this->service->delete($contract);

            return response()->json([
                'success' => true,
                'message' => 'Contrat supprimé avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression.',
                'data' => null,
            ], 500);
        }
    }

    public function getExpiringContracts(Request $request): JsonResponse
    {
        try {
            $jours = $request->input('jours', 30);
            $contrats = $this->service->getExpiringContracts($jours);

            return response()->json([
                'success' => true,
                'message' => "Contrats expirant dans {$jours} jours.",
                'data' => [
                    'jours' => $jours,
                    'total' => $contrats->count(),
                    'contrats' => ContractResource::collection($contrats),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération.',
                'data' => null,
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = $this->service->getStats();

            return response()->json([
                'success' => true,
                'message' => 'Statistiques récupérées.',
                'data' => $stats,
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
     * Liste des contrats supprimés (corbeille).
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $contrats = Contrat::onlyTrashed()
                ->with(['employe'])
                ->orderBy('deleted_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Contrats archivés récupérés avec succès.',
                'data' => ContractResource::collection($contrats),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des contrats archivés : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Restaurer un contrat supprimé.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $contrat = Contrat::onlyTrashed()->findOrFail($id);
            
            // Utiliser le repository pour restaurer l'état aussi
            $this->service->getContracts([], 1); // Juste pour vérifier le service existe
            // On appelle directement le repository via le service ou on fait la logique ici
            $contrat->etat = $contrat->etat_avant_archivage ?? $contrat->etat;
            $contrat->etat_avant_archivage = null;
            $contrat->save();
            $contrat->restore();

            return response()->json([
                'success' => true,
                'message' => 'Contrat restauré avec succès.',
                'data' => new ContractResource($contrat->fresh()),
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

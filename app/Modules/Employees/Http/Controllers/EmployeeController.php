<?php

namespace App\Modules\Employees\Http\Controllers;

use App\Models\User;
use App\Modules\Employees\Http\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Http\Requests\UpdateSelfEmployeeRequest;
use App\Modules\Employees\Http\Resources\EmployeeCollection;
use App\Modules\Employees\Http\Resources\EmployeeResource;
use App\Modules\Employees\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur pour la gestion des employés
 */
class EmployeeController
{
    /**
     * @var EmployeeService
     */
    private EmployeeService $service;

    public function __construct(EmployeeService $service)
    {
        $this->service = $service;
    }

    /**
     * Liste des employés avec pagination et filtres.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'departement_id',
                'est_actif',
                'manager_id',
                'search',
                'avec_contrat_actif',
                'sort_by',
                'sort_order',
            ]);

            $perPage = $request->input('per_page', 15);

            $employees = $this->service->getEmployees($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employés récupérés avec succès.',
                'data' => new EmployeeCollection($employees),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des employés.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Créer un nouvel employé.
     *
     * @param StoreEmployeeRequest $request
     * @return JsonResponse
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $photo = $request->file('photo');

            $employee = $this->service->createEmployee($data, $photo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employé créé avec succès.',
                'data' => new EmployeeResource($employee),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'employé : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Afficher un employé avec détails.
     *
     * @param User $employee
     * @return JsonResponse
     */
    public function show(User $employee): JsonResponse
    {
        try {
            $employee = $this->service->getEmployee($employee->id);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employé non trouvé.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employé récupéré avec succès.',
                'data' => new EmployeeResource($employee),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'employé.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mettre à jour un employé.
     *
     * @param UpdateEmployeeRequest $request
     * @param User $employee
     * @return JsonResponse
     */
    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $photo = $request->file('photo');

            $employee = $this->service->updateEmployee($employee, $data, $photo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employé mis à jour avec succès.',
                'data' => new EmployeeResource($employee),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'employé : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Supprimer un employé (soft delete).
     *
     * @param User $employee
     * @return JsonResponse
     */
    public function destroy(User $employee): JsonResponse
    {
        try {
            $this->service->deleteEmployee($employee);

            return response()->json([
                'success' => true,
                'message' => 'Employé supprimé avec succès.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mise à jour par l'employé lui-même (champs restreints).
     *
     * @param UpdateSelfEmployeeRequest $request
     * @param User $employee
     * @return JsonResponse
     */
    public function updateSelf(UpdateSelfEmployeeRequest $request, User $employee): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur modifie son propre profil
            if ($request->user()->id !== $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que votre propre profil.',
                    'data' => null,
                ], 403);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $photo = $request->file('photo');

            $employee = $this->service->updateSelf($employee, $data, $photo);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès.',
                'data' => new EmployeeResource($employee),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques des employés.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->service->getStats();

            return response()->json([
                'success' => true,
                'message' => 'Statistiques récupérées avec succès.',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Récupérer les employés supprimés (corbeille).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $employees = $this->service->getTrashedEmployees($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employés archivés récupérés avec succès.',
                'data' => new EmployeeCollection($employees),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des employés archivés : ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Restaurer un employé supprimé.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $employee = $this->service->restoreEmployee($id);

            return response()->json([
                'success' => true,
                'message' => 'Employé restauré avec succès.',
                'data' => new EmployeeResource($employee),
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

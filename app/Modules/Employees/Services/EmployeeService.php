<?php

namespace App\Modules\Employees\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Employees\Repositories\EmployeeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Service pour la gestion des employés
 */
class EmployeeService
{
    /**
     * @var EmployeeRepository
     */
    private EmployeeRepository $repository;

    public function __construct(EmployeeRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Récupérer la liste des employés avec filtres.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $this->repository->getAllPaginated($filters, $perPage, $sortBy, $sortOrder);
    }

    /**
     * Récupérer un employé par ID.
     *
     * @param int $id
     * @return User|null
     */
    public function getEmployee(int $id): ?User
    {
        return $this->repository->findById($id);
    }

    /**
     * Créer un nouvel employé.
     *
     * @param array $data
     * @param UploadedFile|null $photo
     * @return User
     * @throws \Exception
     */
    public function createEmployee(array $data, ?UploadedFile $photo = null): User
    {
        try {
            DB::beginTransaction();

            // Hashage du mot de passe
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                // Mot de passe par défaut
                $data['password'] = Hash::make('password123');
            }

            // Upload de la photo
            if ($photo) {
                $data['photo'] = $this->uploadPhoto($photo);
            }

            // Création de l'employé
            $employee = $this->repository->create($data);

            // Assignation du rôle Employé par défaut
            $employee->assignRole('employe');

            // Log d'activité
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'create_employee',
                'model' => 'User',
                'model_id' => $employee->id,
                'new_values' => $data,
            ]);

            DB::commit();

            return $employee->fresh(['roles', 'contrats']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mettre à jour un employé.
     *
     * @param User $employee
     * @param array $data
     * @param UploadedFile|null $photo
     * @return User
     * @throws \Exception
     */
    public function updateEmployee(User $employee, array $data, ?UploadedFile $photo = null): User
    {
        try {
            DB::beginTransaction();

            $oldValues = $employee->toArray();

            // Upload de la nouvelle photo
            if ($photo) {
                // Supprimer l'ancienne photo
                if ($employee->photo) {
                    Storage::disk('public')->delete($employee->photo);
                }
                $data['photo'] = $this->uploadPhoto($photo);
            }

            // Mise à jour partielle (ne pas écraser avec null)
            $updateData = array_filter($data, function ($value) {
                return $value !== null;
            });

            // Ne pas mettre à jour le mot de passe si non fourni
            if (isset($updateData['password']) && $updateData['password']) {
                $updateData['password'] = Hash::make($updateData['password']);
            } else {
                unset($updateData['password']);
            }

            $employee = $this->repository->update($employee, $updateData);

            // Log d'activité
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'update_employee',
                'model' => 'User',
                'model_id' => $employee->id,
                'old_values' => $oldValues,
                'new_values' => $updateData,
            ]);

            DB::commit();

            return $employee->fresh(['roles', 'contrats']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mise à jour restreinte par l'employé lui-même.
     *
     * @param User $employee
     * @param array $data
     * @param UploadedFile|null $photo
     * @return User
     * @throws \Exception
     */
    public function updateSelf(User $employee, array $data, ?UploadedFile $photo = null): User
    {
        // Champs autorisés pour l'auto-modification
        $allowedFields = ['telephone', 'adresse', 'photo'];
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        try {
            DB::beginTransaction();

            // Upload de la photo
            if ($photo) {
                if ($employee->photo) {
                    Storage::disk('public')->delete($employee->photo);
                }
                $filteredData['photo'] = $this->uploadPhoto($photo);
            }

            $employee = $this->repository->update($employee, $filteredData);

            // Log d'activité
            ActivityLog::create([
                'user_id' => $employee->id,
                'action' => 'update_self',
                'model' => 'User',
                'model_id' => $employee->id,
                'new_values' => $filteredData,
            ]);

            DB::commit();

            return $employee->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Supprimer un employé (soft delete).
     *
     * @param User $employee
     * @return bool
     * @throws \Exception
     */
    public function deleteEmployee(User $employee): bool
    {
        // Vérifier s'il a un contrat actif
        if ($this->repository->hasActiveContracts($employee)) {
            throw new \Exception('Impossible de supprimer un employé avec un contrat actif.');
        }

        try {
            DB::beginTransaction();

            // Log d'activité
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete_employee',
                'model' => 'User',
                'model_id' => $employee->id,
                'old_values' => $employee->toArray(),
            ]);

            $result = $this->repository->delete($employee);

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Uploader une photo.
     *
     * @param UploadedFile $photo
     * @return string
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        $filename = 'employees/' . uniqid() . '_' . time() . '.' . $photo->getClientOriginalExtension();
        return $photo->storeAs('', $filename, 'public');
    }

    /**
     * Récupérer les statistiques.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    /**
     * Récupérer les employés par rôle.
     *
     * @param string $role
     * @return Collection
     */
    public function getByRole(string $role): Collection
    {
        return $this->repository->getByRole($role);
    }

    /**
     * Récupérer les employés supprimés (corbeille).
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTrashedEmployees(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getTrashed($perPage);
    }

    /**
     * Restaurer un employé supprimé.
     *
     * @param string $id
     * @return User
     * @throws \Exception
     */
    public function restoreEmployee(string $id): User
    {
        $employee = $this->repository->findTrashedById($id);

        if (!$employee) {
            throw new \Exception('Employé non trouvé dans la corbeille.');
        }

        try {
            DB::beginTransaction();

            $employee->restore();

            // Log d'activité
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'restore_employee',
                'model' => 'User',
                'model_id' => $employee->id,
                'new_values' => ['restored_at' => now()->toDateTimeString()],
            ]);

            DB::commit();

            return $employee;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

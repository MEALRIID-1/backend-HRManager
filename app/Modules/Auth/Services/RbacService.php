<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service pour la gestion RBAC (Role-Based Access Control)
 */
class RbacService
{
    /**
     * Synchroniser les rôles d'un utilisateur.
     *
     * @param User $user
     * @param array<string> $roles
     * @return User
     * @throws \Exception
     */
    public function syncUserRoles(User $user, array $roles): User
    {
        try {
            DB::beginTransaction();

            $validRoles = Role::whereIn('name', $roles)->pluck('name')->toArray();
            $user->syncRoles($validRoles);

            DB::commit();

            return $user->load('roles');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Récupérer toutes les permissions d'un utilisateur (directes et via rôles).
     *
     * @param User $user
     * @return Collection
     */
    public function getUserPermissions(User $user): Collection
    {
        return $user->getAllPermissions();
    }

    /**
     * Vérifier si l'utilisateur a accès à un module spécifique.
     *
     * @param User $user
     * @param string $module
     * @return bool
     */
    public function hasModuleAccess(User $user, string $module): bool
    {
        $modulePatterns = [
            'employees' => ['employees', 'employe'],
            'contracts' => ['contracts', 'contract'],
            'conges' => ['leaves', 'conge', 'conges'],
            'fiches_paie' => ['payslip', 'payroll', 'paie'],
            'validations' => ['validations', 'validation'],
            'reports' => ['reports', 'report'],
            'rbac' => ['roles', 'permissions', 'rbac'],
        ];

        $patterns = $modulePatterns[$module] ?? [$module];

        $permissions = $user->getAllPermissions();

        foreach ($permissions as $permission) {
            foreach ($patterns as $pattern) {
                if (stripos($permission->name, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Récupérer la matrice des permissions par rôle.
     *
     * @return array
     */
    public function getPermissionsMatrix(): array
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        $matrix = [];

        foreach ($permissions as $permission) {
            $row = [
                'permission' => $permission->name,
                'roles' => [],
            ];

            foreach ($roles as $role) {
                $row['roles'][$role->name] = $role->permissions->contains('id', $permission->id);
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Assigner des permissions à un utilisateur directement.
     *
     * @param User $user
     * @param array<string> $permissions
     * @return User
     * @throws \Exception
     */
    public function assignDirectPermissions(User $user, array $permissions): User
    {
        try {
            DB::beginTransaction();

            $validPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
            $user->givePermissionTo($validPermissions);

            DB::commit();

            return $user->load('permissions');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Révoquer des permissions directes d'un utilisateur.
     *
     * @param User $user
     * @param array<string> $permissions
     * @return User
     * @throws \Exception
     */
    public function revokeDirectPermissions(User $user, array $permissions): User
    {
        try {
            DB::beginTransaction();

            $user->revokePermissionTo($permissions);

            DB::commit();

            return $user->load('permissions');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Récupérer les rôles disponibles pour un utilisateur.
     *
     * @return Collection
     */
    public function getAvailableRoles(): Collection
    {
        return Role::withCount('users')->get();
    }

    /**
     * Récupérer les permissions disponibles.
     *
     * @return Collection
     */
    public function getAvailablePermissions(): Collection
    {
        return Permission::withCount('roles')->get();
    }

    /**
     * Vérifier si un utilisateur peut gérer un autre utilisateur.
     *
     * @param User $manager
     * @param User $employee
     * @return bool
     */
    public function canManage(User $manager, User $employee): bool
    {
        // Un admin peut gérer tout le monde
        if ($manager->hasRole('admin')) {
            return true;
        }

        // Un RH peut gérer tout le monde sauf les admins
        if ($manager->hasRole('rh') && !$employee->hasRole('admin')) {
            return true;
        }

        // Un manager peut gérer ses employés (logique métier à adapter)
        if ($manager->hasRole('manager') && $employee->hasRole('employe')) {
            // Vérifier si l'employé est dans l'équipe du manager
            // Cette logique dépend de la structure organisationnelle
            return $this->isInTeam($manager, $employee);
        }

        return false;
    }

    /**
     * Vérifier si un employé est dans l'équipe d'un manager.
     *
     * @param User $manager
     * @param User $employee
     * @return bool
     */
    private function isInTeam(User $manager, User $employee): bool
    {
        // Implémentation à adapter selon la structure organisationnelle
        // Par exemple, vérifier une table de relations manager-employé
        // Pour l'instant, retourne false par défaut
        return false;
    }

    /**
     * Créer un rôle avec ses permissions.
     *
     * @param string $name
     * @param array<string> $permissions
     * @return Role
     * @throws \Exception
     */
    public function createRoleWithPermissions(string $name, array $permissions = []): Role
    {
        try {
            DB::beginTransaction();

            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            if (!empty($permissions)) {
                $validPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
                $role->syncPermissions($validPermissions);
            }

            DB::commit();

            return $role->load('permissions');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Dupliquer un rôle avec toutes ses permissions.
     *
     * @param Role $sourceRole
     * @param string $newName
     * @return Role
     * @throws \Exception
     */
    public function duplicateRole(Role $sourceRole, string $newName): Role
    {
        try {
            DB::beginTransaction();

            $newRole = Role::create([
                'name' => $newName,
                'guard_name' => $sourceRole->guard_name,
            ]);

            $permissions = $sourceRole->permissions->pluck('name')->toArray();
            $newRole->syncPermissions($permissions);

            DB::commit();

            return $newRole->load('permissions');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

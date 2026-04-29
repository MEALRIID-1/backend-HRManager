<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder pour créer les permissions et les assigner aux rôles
 * selon la matrice définie dans config/permissions.php
 */
class PermissionSeeder extends Seeder
{
    /**
     * Liste complète des permissions par module.
     *
     * @var array
     */
    private array $permissions = [
        // Module Employés
        'view employees',
        'view employee profile',
        'create employees',
        'edit employees',
        'delete employees',
        'manage employees',

        // Module Contrats
        'view contracts',
        'create contracts',
        'edit contracts',
        'terminate contracts',
        'renew contracts',
        'manage contracts',

        // Module Congés
        'view leaves',
        'request leaves',
        'approve leaves first',
        'approve leaves final',
        'reject leaves',
        'cancel leaves',
        'manage leaves',
        'manage leave types',
        'view leave balance',

        // Module Fiches de Paie
        'view payslips',
        'create payslips',
        'edit payslips',
        'delete payslips',
        'generate payslips',
        'send payslips',
        'manage payroll',
        'manage payroll settings',

        // Module Validations
        'view validations',
        'approve validations',
        'reject validations',
        'escalate validations',
        'view validation history',
        'manage validations',

        // Module Rapports
        'view reports',
        'generate reports',
        'export reports',
        'schedule reports',
        'manage reports',
        'view hr reports',
        'view payroll reports',
        'view leave reports',

        // Module RBAC
        'view roles',
        'create roles',
        'edit roles',
        'delete roles',
        'view permissions',
        'assign permissions',
        'revoke permissions',
        'assign roles',
        'manage rbac',
    ];

    /**
     * Permissions par rôle selon la matrice.
     *
     * @var array
     */
    private array $rolePermissions = [
        'admin' => [
            // Toutes les permissions
            'view employees', 'view employee profile', 'create employees', 'edit employees', 'delete employees', 'manage employees',
            'view contracts', 'create contracts', 'edit contracts', 'terminate contracts', 'renew contracts', 'manage contracts',
            'view leaves', 'request leaves', 'approve leaves first', 'approve leaves final', 'reject leaves', 'cancel leaves', 'manage leaves', 'manage leave types', 'view leave balance',
            'view payslips', 'create payslips', 'edit payslips', 'delete payslips', 'generate payslips', 'send payslips', 'manage payroll', 'manage payroll settings',
            'view validations', 'approve validations', 'reject validations', 'escalate validations', 'view validation history', 'manage validations',
            'view reports', 'generate reports', 'export reports', 'schedule reports', 'manage reports', 'view hr reports', 'view payroll reports', 'view leave reports',
            'view roles', 'create roles', 'edit roles', 'delete roles', 'view permissions', 'assign permissions', 'revoke permissions', 'assign roles', 'manage rbac',
        ],
        'rh' => [
            // Employés
            'view employees', 'view employee profile', 'create employees', 'edit employees', 'manage employees',
            // Contrats
            'view contracts', 'create contracts', 'edit contracts', 'terminate contracts', 'renew contracts', 'manage contracts',
            // Congés
            'view leaves', 'request leaves', 'approve leaves first', 'approve leaves final', 'reject leaves', 'cancel leaves', 'manage leaves', 'manage leave types', 'view leave balance',
            // Paie
            'view payslips', 'create payslips', 'edit payslips', 'generate payslips', 'send payslips', 'manage payroll', 'manage payroll settings',
            // Validations
            'view validations', 'approve validations', 'reject validations', 'escalate validations', 'view validation history', 'manage validations',
            // Rapports
            'view reports', 'generate reports', 'export reports', 'schedule reports', 'manage reports', 'view hr reports', 'view payroll reports', 'view leave reports',
            // RBAC - seulement assigner des rôles
            'assign roles',
        ],
        'manager' => [
            // Employés - son équipe uniquement
            'view employees', 'view employee profile', 'edit employees',
            // Contrats - lecture seule
            'view contracts',
            // Congés
            'view leaves', 'request leaves', 'approve leaves first', 'reject leaves', 'cancel leaves', 'view leave balance',
            // Paie - aucun accès aux fiches de paie des autres
            // Validations
            'view validations', 'approve validations', 'reject validations', 'escalate validations', 'view validation history',
            // Rapports - son équipe
            'view reports', 'generate reports', 'export reports', 'view leave reports',
        ],
        'employe' => [
            // Son propre profil
            'view employee profile',
            // Son propre contrat
            'view contracts',
            // Ses propres congés
            'view leaves', 'request leaves', 'cancel leaves', 'view leave balance',
            // Ses propres fiches de paie
            'view payslips',
            // Ses propres validations
            'view validations', 'view validation history',
        ],
    ];

    /**
     * Exécuter le seeder.
     */
    public function run(): void
    {
        // Vérifier/créer la table permissions
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        // Vérifier/créer la table pivot role_has_permissions
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function ($table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

                $table->primary(['permission_id', 'role_id']);
            });
        }

        // Créer les permissions
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Permissions créées: ' . count($this->permissions));

        // Assigner les permissions aux rôles
        foreach ($this->rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (!$roleId) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');
                if (!$permissionId) {
                    continue;
                }

                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
}
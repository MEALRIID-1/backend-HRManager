<?php

namespace App\Providers;

use App\Models\Contrat;
use App\Models\User;
use App\Policies\ContratPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Service Provider pour la gestion des autorisations (Gates & Policies)
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Les policies de l'application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Contrat::class => ContratPolicy::class,
    ];

    /**
     * Enregistrer les services d'authentification.
     */
    public function register(): void
    {
        //
    }

    /**
     * Définir les Gates pour l'application.
     */
    public function boot(): void
    {
        $this->defineEmployeeGates();
        $this->defineContractGates();
        $this->defineLeaveGates();
        $this->definePayrollGates();
        $this->defineValidationGates();
        $this->defineReportGates();
        $this->defineRbacGates();
        $this->defineSuperAdminGate();
    }

    /**
     * Gates pour la gestion des employés
     */
    private function defineEmployeeGates(): void
    {
        Gate::define('view-employees', function (User $user) {
            return $user->hasAnyPermission(['view employees', 'manage employees']);
        });

        Gate::define('create-employees', function (User $user) {
            return $user->hasAnyPermission(['create employees', 'manage employees']);
        });

        Gate::define('edit-employees', function (User $user) {
            return $user->hasAnyPermission(['edit employees', 'manage employees']);
        });

        Gate::define('delete-employees', function (User $user) {
            return $user->hasPermissionTo('manage employees');
        });

        Gate::define('manage-employees', function (User $user) {
            return $user->hasPermissionTo('manage employees');
        });

        Gate::define('view-employee-profile', function (User $user, User $employee) {
            return $user->id === $employee->id ||
                   $user->hasAnyPermission(['view employees', 'manage employees']);
        });
    }

    /**
     * Gates pour la gestion des contrats
     */
    private function defineContractGates(): void
    {
        Gate::define('view-contracts', function (User $user) {
            return $user->hasAnyPermission(['view contracts', 'manage contracts']);
        });

        Gate::define('create-contracts', function (User $user) {
            return $user->hasAnyPermission(['create contracts', 'manage contracts']);
        });

        Gate::define('edit-contracts', function (User $user) {
            return $user->hasAnyPermission(['edit contracts', 'manage contracts']);
        });

        Gate::define('delete-contracts', function (User $user) {
            return $user->hasPermissionTo('manage contracts');
        });

        Gate::define('terminate-contracts', function (User $user) {
            return $user->hasAnyPermission(['terminate contracts', 'manage contracts']);
        });

        Gate::define('renew-contracts', function (User $user) {
            return $user->hasAnyPermission(['renew contracts', 'manage contracts']);
        });

        Gate::define('view-own-contract', function (User $user, $contract) {
            return $user->id === $contract->user_id ||
                   $user->hasAnyPermission(['view contracts', 'manage contracts']);
        });
    }

    /**
     * Gates pour la gestion des congés
     */
    private function defineLeaveGates(): void
    {
        Gate::define('view-leaves', function (User $user) {
            return $user->hasAnyPermission(['view leaves', 'manage leaves']);
        });

        Gate::define('request-leave', function (User $user) {
            return $user->hasAnyPermission(['request leaves', 'manage leaves']);
        });

        Gate::define('approve-leave-first', function (User $user) {
            return $user->hasAnyPermission(['approve leaves first', 'manage leaves']);
        });

        Gate::define('approve-leave-final', function (User $user) {
            return $user->hasAnyPermission(['approve leaves final', 'manage leaves']);
        });

        Gate::define('reject-leaves', function (User $user) {
            return $user->hasAnyPermission(['reject leaves', 'manage leaves']);
        });

        Gate::define('cancel-leaves', function (User $user, $leave) {
            return $user->id === $leave->user_id ||
                   $user->hasAnyPermission(['cancel leaves', 'manage leaves']);
        });

        Gate::define('view-leave-balance', function (User $user, ?User $employee = null) {
            if ($employee === null) {
                return true; // Son propre solde
            }
            return $user->id === $employee->id ||
                   $user->hasAnyPermission(['view leaves', 'manage leaves']);
        });

        Gate::define('manage-leave-types', function (User $user) {
            return $user->hasPermissionTo('manage leave types');
        });
    }

    /**
     * Gates pour la gestion des fiches de paie
     */
    private function definePayrollGates(): void
    {
        Gate::define('view-payslips', function (User $user) {
            return $user->hasAnyPermission(['view payslips', 'manage payroll']);
        });

        Gate::define('create-payslips', function (User $user) {
            return $user->hasAnyPermission(['create payslips', 'manage payroll']);
        });

        Gate::define('edit-payslips', function (User $user) {
            return $user->hasAnyPermission(['edit payslips', 'manage payroll']);
        });

        Gate::define('delete-payslips', function (User $user) {
            return $user->hasPermissionTo('manage payroll');
        });

        Gate::define('generate-payslips', function (User $user) {
            return $user->hasAnyPermission(['generate payslips', 'manage payroll']);
        });

        Gate::define('send-payslips', function (User $user) {
            return $user->hasAnyPermission(['send payslips', 'manage payroll']);
        });

        Gate::define('view-own-payslip', function (User $user, $payslip) {
            return $user->id === $payslip->user_id ||
                   $user->hasAnyPermission(['view payslips', 'manage payroll']);
        });

        Gate::define('manage-payroll-settings', function (User $user) {
            return $user->hasPermissionTo('manage payroll settings');
        });
    }

    /**
     * Gates pour les validations
     */
    private function defineValidationGates(): void
    {
        Gate::define('view-validations', function (User $user) {
            return $user->hasAnyPermission(['view validations', 'manage validations']);
        });

        Gate::define('approve-validations', function (User $user) {
            return $user->hasAnyPermission(['approve validations', 'manage validations']);
        });

        Gate::define('reject-validations', function (User $user) {
            return $user->hasAnyPermission(['reject validations', 'manage validations']);
        });

        Gate::define('escalate-validations', function (User $user) {
            return $user->hasAnyPermission(['escalate validations', 'manage validations']);
        });

        Gate::define('view-validation-history', function (User $user) {
            return $user->hasAnyPermission(['view validation history', 'manage validations']);
        });
    }

    /**
     * Gates pour les rapports
     */
    private function defineReportGates(): void
    {
        Gate::define('view-reports', function (User $user) {
            return $user->hasAnyPermission(['view reports', 'generate reports', 'manage reports']);
        });

        Gate::define('generate-reports', function (User $user) {
            return $user->hasAnyPermission(['generate reports', 'manage reports']);
        });

        Gate::define('export-reports', function (User $user) {
            return $user->hasAnyPermission(['export reports', 'manage reports']);
        });

        Gate::define('schedule-reports', function (User $user) {
            return $user->hasAnyPermission(['schedule reports', 'manage reports']);
        });

        Gate::define('manage-reports', function (User $user) {
            return $user->hasPermissionTo('manage reports');
        });

        Gate::define('view-hr-reports', function (User $user) {
            return $user->hasAnyPermission(['view hr reports', 'generate reports', 'manage reports']);
        });

        Gate::define('view-payroll-reports', function (User $user) {
            return $user->hasAnyPermission(['view payroll reports', 'generate reports', 'manage reports']);
        });

        Gate::define('view-leave-reports', function (User $user) {
            return $user->hasAnyPermission(['view leave reports', 'generate reports', 'manage reports']);
        });
    }

    /**
     * Gates pour la gestion RBAC (Roles & Permissions)
     */
    private function defineRbacGates(): void
    {
        Gate::define('view-roles', function (User $user) {
            return $user->hasAnyPermission(['view roles', 'manage rbac']);
        });

        Gate::define('create-roles', function (User $user) {
            return $user->hasAnyPermission(['create roles', 'manage rbac']);
        });

        Gate::define('edit-roles', function (User $user) {
            return $user->hasAnyPermission(['edit roles', 'manage rbac']);
        });

        Gate::define('delete-roles', function (User $user) {
            return $user->hasPermissionTo('manage rbac');
        });

        Gate::define('view-permissions', function (User $user) {
            return $user->hasAnyPermission(['view permissions', 'manage rbac']);
        });

        Gate::define('assign-permissions', function (User $user) {
            return $user->hasAnyPermission(['assign permissions', 'manage rbac']);
        });

        Gate::define('revoke-permissions', function (User $user) {
            return $user->hasAnyPermission(['revoke permissions', 'manage rbac']);
        });

        Gate::define('assign-roles', function (User $user) {
            return $user->hasAnyPermission(['assign roles', 'manage rbac']);
        });

        Gate::define('manage-rbac', function (User $user) {
            return $user->hasPermissionTo('manage rbac');
        });
    }

    /**
     * Gate Super Admin - accès total
     */
    private function defineSuperAdminGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
            return null;
        });
    }
}

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CongeController;
use App\Http\Controllers\Api\V1\ContratController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FichePaieController;
use App\Http\Controllers\Api\V1\EmployeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParametreController;
use App\Http\Controllers\Api\V1\RapportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Authentification
|--------------------------------------------------------------------------
*/

// Routes publiques
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes protégées - Authentification
Route::prefix('v1/auth')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Routes profil
        Route::get('/profil', [ParametreController::class, 'getProfil']);
        Route::put('/profil', [ParametreController::class, 'updateProfil']);
        Route::post('/profil/photo', [ParametreController::class, 'uploadPhotoProfil']);

        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Employés
|--------------------------------------------------------------------------
*/

Route::prefix('v1/employes')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Corbeille (doit être avant /{id})
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::get('/trashed', [EmployeController::class, 'trashed']);
        });

        // Routes accessibles à tous les utilisateurs authentifiés
        Route::get('/', [EmployeController::class, 'index']);
        Route::get('/{id}', [EmployeController::class, 'show']);

        // Routes admin et RH uniquement
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::post('/', [EmployeController::class, 'store']);
            Route::put('/{id}', [EmployeController::class, 'update']);
            Route::post('/{id}/upload-photo', [EmployeController::class, 'uploadPhoto']);
            Route::post('/{id}/restore', [EmployeController::class, 'restore']);
        });

        // Soft delete (admin, RH, manager)
        Route::middleware(['role:admin,rh,manager'])->group(function () {
            Route::delete('/{id}', [EmployeController::class, 'destroy']);
        });

        // Suppression définitive (admin uniquement)
        Route::middleware(['role:admin'])->group(function () {
            Route::delete('/{id}/force', [EmployeController::class, 'forceDelete']);
        });
    });

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/departements', [ParametreController::class, 'getDepartements']);
        Route::get('/user', [ParametreController::class, 'getProfil']);
        
        // Routes profil - accessibles à tous les utilisateurs authentifiés
        Route::prefix('/parametres/profil')->group(function () {
            Route::get('/', [ParametreController::class, 'getProfil']);
            Route::put('/', [ParametreController::class, 'updateProfil']);
            Route::post('/photo', [ParametreController::class, 'uploadPhotoProfil']);
        });
    });


    
   // ✅ ROUTE POUR LES RÔLES
    Route::prefix('v1')
        ->middleware(['auth:sanctum'])
        ->group(function () {
            Route::get('/roles', [EmployeController::class, 'getRoles']);
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Congés
|--------------------------------------------------------------------------
*/

Route::prefix('v1/conges')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Corbeille (doit être avant /{id})
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::get('/trashed', [CongeController::class, 'trashed']);
        });

        // Routes accessibles à tous les utilisateurs authentifiés
        Route::get('/', [CongeController::class, 'index']);
        Route::get('/mes-conges', [CongeController::class, 'getMyConges']);
        Route::get('/solde', [CongeController::class, 'getSolde']);
        Route::get('/{id}', [CongeController::class, 'show']);

        // Création/modification/suppression (employé pour ses propres congés)
        Route::post('/', [CongeController::class, 'store']);
        Route::put('/{id}', [CongeController::class, 'update']);
        Route::delete('/{id}', [CongeController::class, 'destroy']);

        // Restauration (admin, RH)
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::post('/{id}/restore', [CongeController::class, 'restore']);
        });

        // Validation (manager, RH, admin)
        Route::middleware(['role:admin,rh ,manager'])->group(function () {
            Route::post('/{id}/valider', [CongeController::class, 'valider']);
        });

        // Super validation (admin seulement)
        Route::middleware(['role:admin'])->group(function () {
            Route::post('/{id}/super-validation', [CongeController::class, 'superValidation']);
        });
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Contrats
|--------------------------------------------------------------------------
*/

Route::prefix('v1/contrats')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Corbeille (doit être avant /{id})
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::get('/trashed/list', [ContratController::class, 'trashed']);
        });

        // Routes accessibles à tous les utilisateurs authentifiés
        Route::get('/', [ContratController::class, 'index']);
        Route::get('/{id}', [ContratController::class, 'show']);
        Route::get('/{employeId}/actif', [ContratController::class, 'getContratActif']);

        // Routes admin et RH uniquement
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::post('/', [ContratController::class, 'store']);
            Route::put('/{id}', [ContratController::class, 'update']);
            Route::post('/{id}/restore', [ContratController::class, 'restore']);
            Route::post('/{id}/generer-pdf', [ContratController::class, 'genererPDF']);

            // PDF téléchargement et impression (RH et admin seulement)
            Route::get('/{id}/telecharger', [ContratController::class, 'telecharger']);
            Route::get('/{id}/imprimer', [ContratController::class, 'imprimer']);
            //  Route::get('/{id}/telecharger', [FichePaieController::class, 'telecharger'])->name('fiches-paie.telecharger');
        });

        // Soft delete (admin, RH)
        Route::middleware(['role:admin,rh'])->group(function () {
            Route::delete('/{id}', [ContratController::class, 'destroy']);
        });
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Fiches de Paie
|--------------------------------------------------------------------------
*/

Route::prefix('v1/fiches-paie')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Routes accessibles à tous les rôles authentifiés
        Route::get('/mes-fiches', [FichePaieController::class, 'getMesFiches']);
        Route::get('/{id}', [FichePaieController::class, 'show']);

        // Routes RH/Admin
        Route::middleware(['role:admin,rh'])->group(function () {
           Route::get('/', [FichePaieController::class, 'index']);
            Route::post('/', [FichePaieController::class, 'store']);
            Route::put('/{id}', [FichePaieController::class, 'update']);
            Route::post('/{id}/generer-pdf', [FichePaieController::class, 'genererPDF']);
            Route::get('/{id}/telecharger', [FichePaieController::class, 'telecharger']);
        });

        // Soft delete (admin seulement)
        Route::middleware(['role:admin'])->group(function () {
            Route::delete('/{id}', [FichePaieController::class, 'destroy']);
            Route::get('/trashed', [FichePaieController::class, 'trashed']);
            Route::post('/{id}/restore', [FichePaieController::class, 'restore']);
        });
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Notifications
|--------------------------------------------------------------------------
*/

Route::prefix('v1/notifications')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Routes accessibles à tous les utilisateurs authentifiés
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    });

/*
|--------------------------------------------------------------------------
| API Routes - Dashboard
|--------------------------------------------------------------------------
*/

Route::prefix('v1/dashboard')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Dashboard principal adapté au rôle
        Route::get('/', [DashboardController::class, 'index']);

        // Dashboard spécifiques par rôle
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/admin', [DashboardController::class, 'adminDashboard']);
        });

        Route::middleware(['role:rh'])->group(function () {
            Route::get('/rh', [DashboardController::class, 'rhDashboard']);
        });

        Route::middleware(['role:manager'])->group(function () {
            Route::get('/manager', [DashboardController::class, 'managerDashboard']);
        });

        Route::get('/employe', [DashboardController::class, 'employeDashboard']);
    });

/*
|--------------------------------------------------------------------------
| API Routes - Rapports
|--------------------------------------------------------------------------
*/

Route::prefix('v1/rapports')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Rapports accessibles à tous les rôles
        Route::get('/conges', [RapportController::class, 'rapportConges']);
        Route::get('/employes', [RapportController::class, 'rapportEmployes']);

        // Export PDF/Excel
        Route::get('/{type}/export-pdf', [RapportController::class, 'exportPDF']);
        Route::get('/{type}/export-excel', [RapportController::class, 'exportExcel']);

        // Rapport activité (admin seulement)
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/activite', [RapportController::class, 'rapportActivite']);
        });

        // Nettoyer le cache (admin seulement)
        Route::middleware(['role:admin'])->group(function () {
            Route::post('/clear-cache', [RapportController::class, 'clearCache']);
        });
    });

/*
|--------------------------------------------------------------------------
| API Routes - Gestion des Paramètres (Admin seulement)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/parametres')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        // Rôles
        Route::get('/roles', [ParametreController::class, 'getRoles']);
        Route::post('/roles', [ParametreController::class, 'createRole']);
        Route::put('/roles/{id}', [ParametreController::class, 'updateRole']);
        Route::delete('/roles/{id}', [ParametreController::class, 'deleteRole']);

        // Permissions
        Route::get('/permissions', [ParametreController::class, 'getPermissions']);
        Route::post('/roles/{roleId}/permissions', [ParametreController::class, 'assignPermissionsToRole']);

        // Assignation rôles aux utilisateurs
        Route::post('/users/{userId}/roles', [ParametreController::class, 'assignRoleToUser']);
        Route::delete('/users/{userId}/roles', [ParametreController::class, 'revokeRoleFromUser']);
    });

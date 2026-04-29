<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\MeController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\PermissionController;
use App\Modules\Auth\Http\Controllers\RoleController;
use App\Modules\Audit\Http\Controllers\AuditController;
use App\Modules\Contracts\Http\Controllers\ContractController;
use App\Modules\Employees\Http\Controllers\EmployeeController;
use App\Modules\Leaves\Http\Controllers\LeaveController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// ── TEST ENDPOINTS ──────────────────────────────────────
Route::post('/test-post', function (Request $request) {
    return response()->json([
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
        'is_json' => $request->isJson(),
        'all' => $request->all(),
        'input' => $request->input(),
        'raw' => $request->getContent(),
    ]);
});

Route::get('/test-post', fn() => response()->json(['method' => 'GET', 'message' => 'Test GET OK']));

// ── HEALTH CHECK (robuste, toujours 200) ─────────────────
Route::get('/health', function () {
    $dbStatus = 'ok';
    try { DB::connection()->getPdo(); } catch (\Exception $e) { $dbStatus = 'error'; }

    $redisStatus = 'disabled';
    if (extension_loaded('redis') && config('database.redis.default.host')) {
        try { Redis::ping(); } catch (\Exception $e) { $redisStatus = 'error'; }
    }

    return response()->json([
        'healthy' => ($dbStatus === 'ok' && $redisStatus === 'ok'),
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $dbStatus,
            'redis' => $redisStatus,
        ],
        'version' => config('app.version', '1.0.0'),
    ], 200);
});

// ── USER INFO ───────────────────────────────────────────
Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

// ── AUTHENTICATION ROUTES ───────────────────────────────
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // ── EMPLOYÉ (Endpoints /me/*) ───────────────────────
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/me/photo', [MeController::class, 'uploadPhoto']);
    Route::put('/me/password', [MeController::class, 'changePassword']);
    Route::get('/me/contrats', [MeController::class, 'getContrats']);
    Route::get('/me/contrats/actif', [MeController::class, 'getContratActif']);
    Route::get('/me/conges', [MeController::class, 'getConges']);
    Route::get('/me/conges/solde', [MeController::class, 'getSoldeConges']);
    Route::get('/me/conges/corbeille/count', [MeController::class, 'getCorbeilleCount']);
    Route::post('/me/conges', [MeController::class, 'createConge']);
    Route::delete('/me/conges/{id}', [MeController::class, 'cancelConge']);
    Route::put('/me/conges/{id}/restaurer', [MeController::class, 'restoreConge']);

    // ── EMPLOYEES ───────────────────────────────────────
    Route::get('/employees/stats', [EmployeeController::class, 'stats'])->middleware('can:view-employees');
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('can:view-employees');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('can:create-employees');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->middleware('can:view-employee-profile,employee');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('can:edit-employees');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('can:delete-employees');
    Route::post('/employees/{employee}/update-self', [EmployeeController::class, 'updateSelf']);
    Route::get('/employees/trashed', [EmployeeController::class, 'trashed'])->middleware('can:view-employees');
    Route::put('/employees/{id}/restore', [EmployeeController::class, 'restore'])->middleware('can:edit-employees');

    // ── CONTRACTS ───────────────────────────────────────
    Route::get('/contracts/stats', [ContractController::class, 'stats'])->middleware('can:view-contracts');
    Route::get('/contracts/expiring', [ContractController::class, 'getExpiringContracts'])->middleware('can:view-contracts');
    Route::get('/contracts', [ContractController::class, 'index'])->middleware('can:view-contracts');
    Route::post('/contracts', [ContractController::class, 'store'])->middleware('can:create-contracts');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->middleware('can:view,contract');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->middleware('can:update,contract');
    Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate'])->middleware('can:terminate,contract');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->middleware('can:delete-contracts');
    Route::get('/contracts/trashed', [ContractController::class, 'trashed'])->middleware('can:view-contracts');
    Route::put('/contracts/{id}/restore', [ContractController::class, 'restore'])->middleware('can:edit-contracts');

    // ── CONGÉS / LEAVES ──────────────────────────────────
    Route::get('/conges', [LeaveController::class, 'index'])->middleware('can:view-leaves');
    Route::post('/conges', [LeaveController::class, 'store'])->middleware('can:create-leaves');
    Route::get('/conges/{conge}', [LeaveController::class, 'show'])->middleware('can:view-leaves');
    Route::put('/conges/{conge}', [LeaveController::class, 'update'])->middleware('can:edit-leaves');
    Route::delete('/conges/{conge}', [LeaveController::class, 'destroy'])->middleware('can:delete-leaves');
    Route::post('/conges/{conge}/approve', [LeaveController::class, 'approve'])->middleware('can:approve-leaves');
    Route::post('/conges/{conge}/reject', [LeaveController::class, 'reject'])->middleware('can:reject-leaves');
    Route::post('/conges/{conge}/super-valider', [LeaveController::class, 'superValider']);
    Route::get('/conges/trashed', [LeaveController::class, 'trashed'])->middleware('can:view-leaves');
    Route::put('/conges/{id}/restore', [LeaveController::class, 'restore'])->middleware('can:edit-leaves');

    // ── ROLES ───────────────────────────────────────────
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:view-roles');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:create-roles');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('can:view-roles');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:edit-roles');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:delete-roles');
    Route::post('/roles/assign', [RoleController::class, 'assignRole'])->middleware('can:assign-roles');
    Route::post('/roles/remove', [RoleController::class, 'removeRole'])->middleware('can:assign-roles');

    // ── PERMISSIONS ─────────────────────────────────────
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:view-permissions');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('can:manage-rbac');
    Route::get('/permissions/by-module', [PermissionController::class, 'byModule'])->middleware('can:view-permissions');
    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->middleware('can:view-permissions');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('can:manage-rbac');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('can:manage-rbac');
    Route::post('/permissions/assign-to-role', [PermissionController::class, 'assignToRole'])->middleware('can:assign-permissions');
    Route::post('/permissions/revoke-from-role', [PermissionController::class, 'revokeFromRole'])->middleware('can:revoke-permissions');

    // ── NOTIFICATIONS ───────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/recent', [NotificationController::class, 'getRecent']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::get('/notifications/count-non-lues', [NotificationController::class, 'getUnreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/{notification}/lire', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/lire-tout', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // ── AUDIT ───────────────────────────────────────────
    Route::get('/audit/logs', [AuditController::class, 'index']);
    Route::get('/audit/timeline/{entity}/{id}', [AuditController::class, 'timeline']);
    Route::get('/audit/user/{user}/activity', [AuditController::class, 'userActivity']);
    Route::get('/audit/stats', [AuditController::class, 'stats']);
    Route::get('/audit/entities', [AuditController::class, 'entities']);
    Route::get('/audit/actions', [AuditController::class, 'actions']);

    // ── FILES ───────────────────────────────────────────
    Route::get('/files/download/{type}/{id}', [FileController::class, 'download']);
    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::post('/files/photo/{userId}', [FileController::class, 'uploadPhoto']);
    Route::delete('/files/delete', [FileController::class, 'delete']);
    Route::get('/files/metadata', [FileController::class, 'metadata']);

    // ── REPORTS / RAPPORTS (compatibilité frontend)
    Route::get('/reports/dashboard', [\App\Modules\Reports\Http\Controllers\DashboardController::class, 'index'])->middleware('can:view-reports');
    Route::get('/reports/leaves', [\App\Modules\Reports\Http\Controllers\DashboardController::class, 'leaves'])->middleware('can:view-reports');
    Route::get('/reports/employees', [\App\Modules\Reports\Http\Controllers\DashboardController::class, 'employees'])->middleware('can:view-reports');
    Route::get('/reports/charts', [\App\Modules\Reports\Http\Controllers\DashboardController::class, 'chartData'])->middleware('can:view-reports');
    Route::get('/reports/{type}/export', [\App\Modules\Reports\Http\Controllers\DashboardController::class, 'exportReport'])->where('type', 'leaves|employees')->middleware('can:export-reports');

});

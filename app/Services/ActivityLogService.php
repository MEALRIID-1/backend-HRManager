<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Enregistrer une activité dans les logs
     */
    public function log(
        string $action,
        string $module,
        string $description,  // ✅ Doit être une string, pas un array
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?int $userId = null,
        ?string $userName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ActivityLog {
        // Si userName n'est pas fourni mais userId l'est, on récupère le nom
        if ($userName === null && $userId !== null) {
            $user = User::find($userId);
            if ($user) {
                $userName = $user->nom . ' ' . $user->prenom;
            }
        }

        return ActivityLog::create([
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'module' => $module,
            'description' => $description,  // ✅ S'assurer que c'est une string
            'ip_address' => $ipAddress ?? Request::ip(),
            'user_agent' => $userAgent ?? Request::userAgent(),
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
        ]);
    }
}
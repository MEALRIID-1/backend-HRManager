<?php

namespace App\Http\Middleware;

use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de vérification d'accès sécurisé aux fichiers.
 * Vérifie que l'utilisateur est autorisé à accéder au fichier demandé.
 */
class SecureFileAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        // Vérifier l'ownership selon le type de fichier
        $type = $request->route('type');
        $id = $request->route('id');

        if (!$this->canAccess($user, $type, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous n\'avez pas les permissions pour ce fichier.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Vérifie si l'utilisateur peut accéder au fichier.
     */
    private function canAccess($user, string $type, int $id): bool
    {
        return match ($type) {
            'photo' => $this->canAccessPhoto($user, $id),
            'contrat' => $this->canAccessContrat($user, $id),
            'payslip' => $this->canAccessPayslip($user, $id),
            'document' => $this->canAccessDocument($user, $id),
            default => false,
        };
    }

    /**
     * Accès aux photos de profil.
     * - L'utilisateur peut voir sa propre photo
     * - Admin, RH, Manager peuvent voir les photos de leurs subordonnés
     */
    private function canAccessPhoto($user, int $userId): bool
    {
        // Propre photo
        if ($user->id === $userId) {
            return true;
        }

        // Admin et RH peuvent voir toutes les photos
        if ($user->hasRole(['admin', 'rh'])) {
            return true;
        }

        // Manager peut voir les photos de son équipe
        if ($user->hasRole('manager')) {
            $targetUser = User::find($userId);
            return $targetUser && $targetUser->manager_id === $user->id;
        }

        return false;
    }

    /**
     * Accès aux documents de contrat.
     * - L'employé concerné peut voir son contrat
     * - Admin et RH peuvent voir tous les contrats
     * - Manager peut voir les contrats de son équipe
     */
    private function canAccessContrat($user, int $contratId): bool
    {
        $contrat = Contrat::find($contratId);

        if (!$contrat) {
            return false;
        }

        // Propre contrat
        if ($user->id === $contrat->employe_id) {
            return true;
        }

        // Admin et RH
        if ($user->hasRole(['admin', 'rh'])) {
            return true;
        }

        // Manager de l'employé
        if ($user->hasRole('manager')) {
            $employe = User::find($contrat->employe_id);
            return $employe && $employe->manager_id === $user->id;
        }

        return false;
    }

    /**
     * Accès aux fiches de paie.
     * - L'employé concerné peut voir ses fiches
     * - Admin et RH peuvent voir toutes les fiches
     * (Les managers n'ont PAS accès aux fiches de paie)
     */
    private function canAccessPayslip($user, int $payslipId): bool
    {
        $payslip = FichePaie::find($payslipId);

        if (!$payslip) {
            return false;
        }

        // Propre fiche de paie
        if ($user->id === $payslip->employe_id) {
            return true;
        }

        // Admin et RH uniquement (pas les managers pour les fiches de paie)
        return $user->hasRole(['admin', 'rh']);
    }

    /**
     * Accès aux documents génériques.
     * Logique personnalisable selon les besoins.
     */
    private function canAccessDocument($user, int $documentId): bool
    {
        // Par défaut, seuls admin et RH
        return $user->hasRole(['admin', 'rh']);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeService
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function createEmploye(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                $password = $data['mot_de_passe'] ?? $this->authService->generateTemporaryPassword();

                $user = User::create([
                    'is_active'    => true,
                    'email'        => $data['email'],
                    'mot_de_passe' => $password,
                    'nom'          => $data['nom'],
                    'prenom'       => $data['prenom'],
                    'departement'  => $data['departement'] ?? null,
                    'date_embauche' => $data['date_embauche'] ?? now(),
                    'iban'         => $data['iban'] ?? null,
                ]);

                if (isset($data['role_ids'])) {
                    $user->roles()->sync($data['role_ids']);
                }

                // ✅ Correction : Suppression du paramètre 'newValues'
                $this->activityLogService->log(
                    action: 'create',
                    module: 'employes',
                    description: "Création de l'employé {$user->prenom} {$user->nom}",
                    referenceId: $user->id,
                    referenceType: 'User',
                    userId: auth()->id(),
                    userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                );

                Notification::create([
                    'user_id' => $user->id,
                    'type'    => 'compte_cree',
                    'titre'   => 'Compte créé',
                    'message' => "Votre compte a été créé. Mot de passe temporaire: {$password}",
                    'lu'      => false,
                ]);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur création employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un employé
     */
    public function update(User $employe, array $data): User
    {
        return DB::transaction(function () use ($employe, $data) {
            // Mettre à jour l'employé
            $employe->update($data);
            
            // Gérer les rôles si présents
            if (isset($data['role_ids']) && !empty($data['role_ids'])) {
                $employe->roles()->sync($data['role_ids']);
            }
            
            // Charger les relations
            $employe->load('roles');
            
            // ✅ Correction : Version simplifiée sans détails des changements
            $this->activityLogService->log(
                action: 'update',
                module: 'employes',
                description: "Employé {$employe->prenom} {$employe->nom} modifié",
                referenceId: $employe->id,
                referenceType: 'User',
                userId: auth()->id(),
                userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );
            
            return $employe;
        });
    }

    public function destroy(User $user): bool
    {
        try {
            return DB::transaction(function () use ($user) {
                $result = $user->delete();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'employes',
                    description: "Suppression de l'employé {$user->prenom} {$user->nom}",
                    referenceId: $user->id,
                    referenceType: 'User',
                    userId: auth()->id(),
                    userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                );

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur suppression employé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function restore(int $id): User
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = User::withTrashed()->findOrFail($id);
                $user->restore();

                $this->activityLogService->log(
                    action: 'restore',
                    module: 'employes',
                    description: "Restauration de l'employé {$user->prenom} {$user->nom}",
                    referenceId: $user->id,
                    referenceType: 'User',
                    userId: auth()->id(),
                    userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                );

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur restauration employé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function forceDelete(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = User::withTrashed()->findOrFail($id);
                $user->roles()->detach();
                $user->tokens()->delete();

                $this->activityLogService->log(
                    action: 'force_delete',
                    module: 'employes',
                    description: "Suppression définitive de l'employé {$user->prenom} {$user->nom}",
                    referenceId: $id,
                    referenceType: 'User',
                    userId: auth()->id(),
                    userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                );

                return $user->forceDelete();
            });
        } catch (\Exception $e) {
            Log::error('Erreur suppression définitive: ' . $e->getMessage());
            throw $e;
        }
    }

    public function uploadPhoto(User $user, $file): string
    {
        try {
            return DB::transaction(function () use ($user, $file) {
                if ($user->photo_profil) {
                    Storage::disk('public')->delete($user->photo_profil);
                }

                $path = $file->store('photos', 'public');
                $user->update(['photo_profil' => $path]);

                $this->activityLogService->log(
                    action: 'upload_photo',
                    module: 'employes',
                    description: "Upload photo de {$user->prenom} {$user->nom}",
                    referenceId: $user->id,
                    referenceType: 'User',
                    userId: auth()->id(),
                    userName: auth()->user()?->nom . ' ' . auth()->user()?->prenom,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent(),
                );

                return $path;
            });
        } catch (\Exception $e) {
            Log::error('Erreur upload photo: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getEmployeWithDetails(int $id): ?User
    {
        return User::with([
            'roles',
            'roles.permissions',
            'contrats' => fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()),
            'conges'   => fn($q) => $q->whereYear('date_debut', now()->year),
        ])->find($id);
    }

   public function list(array $filters = [], ?User $manager = null, int $perPage = 15): LengthAwarePaginator
{
    // ⚠️ IMPORTANT: Ne pas filtrer les soft deletes
    $query = User::with(['roles']);
    
    // ✅ Ajouter un log pour déboguer
    \Log::info('Liste employés - Nombre total avant filtres: ' . User::count());
    
    // Filtres
    if (isset($filters['nom']) && !empty($filters['nom'])) {
        $search = $filters['nom'];
        $query->where(function($q) use ($search) {
            $q->where('nom', 'like', "%{$search}%")
              ->orWhere('prenom', 'like', "%{$search}%");
        });
    }

    if (isset($filters['departement']) && !empty($filters['departement'])) {
        $query->where('departement', $filters['departement']);
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $query->where('is_active', $filters['is_active']);
    }

    $result = $query->paginate($perPage);
    
    \Log::info('Liste employés - Résultat après filtres: ' . $result->total());
    
    return $result;
}

    public function trashed(int $perPage = 15): LengthAwarePaginator
    {
        return User::onlyTrashed()->with(['roles'])->paginate($perPage);
    }

    public function calculerSoldeConges(User $user): array
    {
        $totalAccorde = 25;
        $joursPris = $user->conges()->where('statut', 'approuve')->whereYear('date_debut', now()->year)->count();

        return [
            'total_accorde'  => $totalAccorde,
            'jours_pris'     => $joursPris,
            'solde_restant'  => $totalAccorde - $joursPris,
        ];
    }

    public function getContratActif(User $user): ?Contrat
    {
        return $user->contrats()
            ->where('statut', 'actif')
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()))
            ->first();
    }

    public function getStatsByDepartement(): array
    {
        return User::selectRaw('departement, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as actifs')
            ->whereNotNull('departement')
            ->groupBy('departement')
            ->get()
            ->map(fn($item) => [
                'departement' => $item->departement,
                'total'       => $item->total,
                'actifs'      => $item->actifs,
                'inactifs'    => $item->total - $item->actifs,
            ])->toArray();
    }
}
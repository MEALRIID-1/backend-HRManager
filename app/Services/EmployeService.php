<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Créer un employé avec assignation de rôle et génération de mot de passe.
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function createEmploye(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                $password = $this->authService->generateTemporaryPassword();

                $user = User::create([
                    'is_active' => true,
                    'email' => $data['email'],
                    'mot_de_passe' => Hash::make($password),
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'departement' => $data['departement'] ?? null,
                    'date_embauche' => $data['date_embauche'] ?? now(),
                    'iban' => $data['iban'] ?? null,
                ]);

                // Assigner les rôles
                if (isset($data['role_ids'])) {
                    $user->roles()->sync($data['role_ids']);
                }

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'entity_name' => 'employe',
                    'new_value' => json_encode($user->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                // Créer une notification pour le nouvel employé
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'compte_cree',
                    'message' => "Votre compte a été créé. Mot de passe temporaire: {$password}",
                    'statut' => 'non_lue',
                    'date_envoi' => now(),
                ]);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un employé avec tous ses détails (eager loading optimisé).
     */
    public function getEmployeWithDetails(int $id): ?User
    {
        return User::with([
            'roles',
            'roles.permissions',
            'contrats' => function ($query) {
                $query->whereNull('date_fin')->orWhere('date_fin', '>=', now());
            },
            'conges' => function ($query) {
                $query->whereYear('date_debut', now()->year);
            },
        ])->find($id);
    }

    /**
     * Liste paginée avec filtres.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], ?User $manager = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles']);

        // Si manager, filtrer sur son département uniquement
        if ($manager && !$manager->roles->contains('nom', 'admin')) {
            $query->where('departement', $manager->departement);
        }

        // Filtre par nom (nom ou prenom)
        if (isset($filters['nom'])) {
            $search = $filters['nom'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%");
            });
        }

        // Filtre par département
        if (isset($filters['departement'])) {
            $query->where('departement', $filters['departement']);
        }

        // Filtre par rôle
        if (isset($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('nom', $filters['role']);
            });
        }

        // Filtre par statut (actif/inactif)
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Liste des employés en corbeille (soft deleted).
     */
    public function trashed(int $perPage = 15): LengthAwarePaginator
    {
        return User::onlyTrashed()->with(['roles'])->paginate($perPage);
    }

    /**
     * Mettre à jour un employé avec logs d'activité.
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function update(User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $oldValues = $user->toArray();

                $user->update([
                    'nom' => $data['nom'] ?? $user->nom,
                    'prenom' => $data['prenom'] ?? $user->prenom,
                    'email' => $data['email'] ?? $user->email,
                    'departement' => $data['departement'] ?? $user->departement,
                    'photo_profil' => $data['photo_profil'] ?? $user->photo_profil,
                    'date_embauche' => $data['date_embauche'] ?? $user->date_embauche,
                    'iban' => $data['iban'] ?? $user->iban,
                    'is_active' => $data['is_active'] ?? $user->is_active,
                ]);

                // Mettre à jour les rôles
                if (isset($data['role_ids'])) {
                    $user->roles()->sync($data['role_ids']);
                }

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'entity_name' => 'employe',
                    'old_value' => json_encode($oldValues),
                    'new_value' => json_encode($user->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete d'un employé.
     *
     * @throws \Exception
     */
    public function destroy(User $user): bool
    {
        try {
            return DB::transaction(function () use ($user) {
                $oldValues = $user->toArray();

                $result = $user->delete();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'delete',
                    'entity_name' => 'employe',
                    'old_value' => json_encode($oldValues),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer un employé supprimé.
     *
     * @throws \Exception
     */
    public function restore(int $id): User
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = User::withTrashed()->findOrFail($id);
                $user->restore();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'restore',
                    'entity_name' => 'employe',
                    'new_value' => json_encode($user->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la restauration de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Suppression définitive (admin seulement).
     *
     * @throws \Exception
     */
    public function forceDelete(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                $user = User::withTrashed()->findOrFail($id);

                // Supprimer les relations
                $user->roles()->detach();
                $user->tokens()->delete();

                $result = $user->forceDelete();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'force_delete',
                    'entity_name' => 'employe',
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression définitive: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload photo de profil.
     *
     * @throws \Exception
     */
    public function uploadPhoto(User $user, $file): string
    {
        try {
            return DB::transaction(function () use ($user, $file) {
                // Supprimer l'ancienne photo si existe
                if ($user->photo_profil) {
                    Storage::disk('public')->delete($user->photo_profil);
                }

                // Stocker la nouvelle photo
                $path = $file->store('photos', 'public');

                $user->update(['photo_profil' => $path]);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'upload_photo',
                    'entity_name' => 'employe',
                    'new_value' => json_encode(['photo_profil' => $path]),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $path;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'upload de la photo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculer le solde de congés d'un employé.
     */
    public function calculerSoldeConges(User $user): array
    {
        $totalAccorde = 25; // Jours par an
        $joursPris = $user->conges()
            ->where('etat', 'approuve')
            ->whereYear('date_debut', now()->year)
            ->count();

        return [
            'total_accorde' => $totalAccorde,
            'jours_pris' => $joursPris,
            'solde_restant' => $totalAccorde - $joursPris,
        ];
    }

    /**
     * Récupérer le contrat actif d'un employé.
     */
    public function getContratActif(User $user): ?Contrat
    {
        return $user->contrats()
            ->where('etat', 'actif')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', now());
            })
            ->first();
    }

    /**
     * Stats par département pour dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStatsByDepartement(): array
    {
        $stats = User::selectRaw('departement, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as actifs')
            ->whereNotNull('departement')
            ->groupBy('departement')
            ->get();

        return $stats->map(function ($item) {
            return [
                'departement' => $item->departement,
                'total' => $item->total,
                'actifs' => $item->actifs,
                'inactifs' => $item->total - $item->actifs,
            ];
        })->toArray();
    }
}

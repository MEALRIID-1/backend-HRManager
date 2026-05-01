<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParametreService
{
    /**
     * Synchroniser les permissions d'un rôle.
     *
     * @param Role $role
     * @param array<int> $permissionIds
     * @return void
     */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        try {
            DB::transaction(function () use ($role, $permissionIds) {
                $role->permissions()->sync($permissionIds);
            });
        } catch (\Exception $e) {
            Log::error('Erreur synchronisation permissions: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer toutes les permissions d'un utilisateur via ses rôles.
     *
     * @param User $user
     * @return Collection<int, Permission>
     */
    public function getUserPermissions(User $user): Collection
    {
        try {
            return Permission::whereHas('roles', function ($query) use ($user) {
                $query->whereIn('roles.id', $user->roles->pluck('id'));
            })->distinct()->get();
        } catch (\Exception $e) {
            Log::error('Erreur récupération permissions utilisateur: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Vérifier si un utilisateur a une permission spécifique.
     *
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function canAccess(User $user, string $permission): bool
    {
        try {
            // Admin a toujours accès
            if ($user->roles->contains('slug', 'admin')) {
                return true;
            }

            // Vérifier la permission via les rôles
            return Permission::where('slug', $permission)
                ->whereHas('roles', function ($query) use ($user) {
                    $query->whereIn('roles.id', $user->roles->pluck('id'));
                })
                ->exists();
        } catch (\Exception $e) {
            Log::error('Erreur vérification accès: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les permissions groupées par module.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    public function getPermissionsByModule(): Collection
    {
        try {
            return Permission::all()->groupBy('module');
        } catch (\Exception $e) {
            Log::error('Erreur récupération permissions par module: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Assigner un rôle à un utilisateur.
     *
     * @param User $user
     * @param int $roleId
     * @return void
     */
    public function assignRoleToUser(User $user, int $roleId): void
    {
        try {
            DB::transaction(function () use ($user, $roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            });
        } catch (\Exception $e) {
            Log::error('Erreur assignation rôle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retirer un rôle à un utilisateur.
     *
     * @param User $user
     * @param int $roleId
     * @return void
     */
    public function revokeRoleFromUser(User $user, int $roleId): void
    {
        try {
            DB::transaction(function () use ($user, $roleId) {
                $user->roles()->detach($roleId);
            });
        } catch (\Exception $e) {
            Log::error('Erreur révocation rôle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Créer un nouveau rôle.
     *
     * @param array<string, mixed> $data
     * @return Role
     */
    public function createRole(array $data): Role
    {
        try {
            return DB::transaction(function () use ($data) {
                $role = Role::create([
                    'nom' => $data['nom'],
                    'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['nom']),
                    'description' => $data['description'] ?? null,
                    'niveau_validation' => $data['niveau_validation'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                if (isset($data['permissions'])) {
                    $role->permissions()->attach($data['permissions']);
                }

                return $role;
            });
        } catch (\Exception $e) {
            Log::error('Erreur création rôle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour un rôle.
     *
     * @param Role $role
     * @param array<string, mixed> $data
     * @return Role
     */
    public function updateRole(Role $role, array $data): Role
    {
        try {
            return DB::transaction(function () use ($role, $data) {
                $role->update([
                    'nom' => $data['nom'] ?? $role->nom,
                    'description' => $data['description'] ?? $role->description,
                    'niveau_validation' => $data['niveau_validation'] ?? $role->niveau_validation,
                    'is_active' => $data['is_active'] ?? $role->is_active,
                ]);

                if (isset($data['permissions'])) {
                    $role->permissions()->sync($data['permissions']);
                }

                return $role->fresh(['permissions']);
            });
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour rôle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer un rôle (soft delete).
     *
     * @param Role $role
     * @return bool
     */
    public function deleteRole(Role $role): bool
    {
        try {
            return DB::transaction(function () use ($role) {
                // Détacher toutes les permissions
                $role->permissions()->detach();

                // Détacher tous les utilisateurs
                $role->users()->detach();

                // Soft delete
                return $role->delete();
            });
        } catch (\Exception $e) {
            Log::error('Erreur suppression rôle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour le profil utilisateur.
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return User
     */
    public function updateProfil(User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $user->update([
                    'nom' => $data['nom'] ?? $user->nom,
                    'prenom' => $data['prenom'] ?? $user->prenom,
                    'email' => $data['email'] ?? $user->email,
                    'telephone' => $data['telephone'] ?? $user->telephone,
                    'adresse' => $data['adresse'] ?? $user->adresse,
                ]);

                return $user->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour profil: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload photo de profil.
     *
     * @param User $user
     * @param \Illuminate\Http\UploadedFile $photo
     * @return User
     */
    public function uploadPhotoProfil(User $user, $photo): User
    {
        try {
            return DB::transaction(function () use ($user, $photo) {
                // Supprimer l'ancienne photo si existe
                if ($user->photo_profil) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo_profil);
                }

                // Stocker la nouvelle photo
                $path = $photo->store('photos-profil', 'public');

                $user->update(['photo_profil' => $path]);

                return $user->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Erreur upload photo profil: ' . $e->getMessage());
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function create(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                $password = $data['password'] ?? $this->generateTemporaryPassword();

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'matricule' => $data['matricule'] ?? $this->generateMatricule(),
                    'telephone' => $data['telephone'] ?? null,
                    'adresse' => $data['adresse'] ?? null,
                    'date_naissance' => $data['date_naissance'] ?? null,
                    'date_embauche' => $data['date_embauche'] ?? Carbon::now(),
                    'poste' => $data['poste'] ?? null,
                    'departement' => $data['departement'] ?? null,
                    'salaire_brut' => $data['salaire_brut'] ?? null,
                    'manager_id' => $data['manager_id'] ?? null,
                    'statut' => User::STATUT_ACTIF,
                    'dernier_changement_password' => Carbon::now(),
                ]);

                if (isset($data['roles'])) {
                    $user->roles()->sync($data['roles']);
                }

                ActivityLog::log(
                    ActivityLog::ACTION_CREATE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Création de l'employé {$user->name}",
                    $user,
                    null,
                    $user->toArray(),
                    auth()->user()
                );

                $this->notificationService->notifyNewAccount($user, $password);

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function update(User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $oldValues = $user->toArray();

                $user->update([
                    'name' => $data['name'] ?? $user->name,
                    'email' => $data['email'] ?? $user->email,
                    'telephone' => $data['telephone'] ?? $user->telephone,
                    'adresse' => $data['adresse'] ?? $user->adresse,
                    'date_naissance' => $data['date_naissance'] ?? $user->date_naissance,
                    'poste' => $data['poste'] ?? $user->poste,
                    'departement' => $data['departement'] ?? $user->departement,
                    'salaire_brut' => $data['salaire_brut'] ?? $user->salaire_brut,
                    'manager_id' => $data['manager_id'] ?? $user->manager_id,
                    'statut' => $data['statut'] ?? $user->statut,
                ]);

                if (isset($data['roles'])) {
                    $user->roles()->sync($data['roles']);
                }

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Modification de l'employé {$user->name}",
                    $user,
                    $oldValues,
                    $user->toArray(),
                    auth()->user()
                );

                return $user;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(User $user): bool
    {
        try {
            return DB::transaction(function () use ($user) {
                $oldValues = $user->toArray();

                $name = $user->name;
                $result = $user->delete();

                ActivityLog::log(
                    ActivityLog::ACTION_DELETE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Suppression de l'employé {$name}",
                    null,
                    $oldValues,
                    null,
                    auth()->user()
                );

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de l\'employé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function findById(int $id): ?User
    {
        return User::with(['roles', 'roles.permissions', 'manager', 'subordonnes'])->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles', 'manager']);

        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (isset($filters['departement'])) {
            $query->where('departement', $filters['departement']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function getManagers(): Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->where('slug', 'manager')
                ->orWhere('slug', 'admin');
        })->get();
    }

    public function changePassword(User $user, string $newPassword): void
    {
        try {
            DB::transaction(function () use ($user, $newPassword) {
                $user->update([
                    'password' => Hash::make($newPassword),
                    'dernier_changement_password' => Carbon::now(),
                    'tentatives_connexion' => 0,
                    'verrouille_jusqua' => null,
                ]);

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Changement de mot de passe pour {$user->name}",
                    $user,
                    null,
                    null,
                    auth()->user() ?? $user
                );
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de mot de passe: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateProfilePhoto(User $user, string $photoPath): void
    {
        try {
            $oldPhoto = $user->photo;

            $user->update(['photo' => $photoPath]);

            if ($oldPhoto && file_exists(storage_path('app/public/' . $oldPhoto))) {
                unlink(storage_path('app/public/' . $oldPhoto));
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la photo: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deactivate(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $user->update(['statut' => User::STATUT_INACTIF]);
                $user->tokens()->delete();

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Désactivation du compte de {$user->name}",
                    $user,
                    ['statut' => User::STATUT_ACTIF],
                    ['statut' => User::STATUT_INACTIF],
                    auth()->user()
                );
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la désactivation du compte: ' . $e->getMessage());
            throw $e;
        }
    }

    public function activate(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $user->update(['statut' => User::STATUT_ACTIF]);

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_EMPLOYE,
                    "Réactivation du compte de {$user->name}",
                    $user,
                    ['statut' => User::STATUT_INACTIF],
                    ['statut' => User::STATUT_ACTIF],
                    auth()->user()
                );
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la réactivation du compte: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function generateMatricule(): string
    {
        $prefix = 'EMP';
        $year = date('Y');
        $lastUser = User::where('matricule', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastUser) {
            $parts = explode('-', $lastUser->matricule);
            $number = (int) end($parts) + 1;
        } else {
            $number = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $number);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * Authentifier l'utilisateur et créer un token.
     *
     * @param array<string, mixed> $credentials
     * @param string $deviceName
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws AuthenticationException
     */
    public function login(array $credentials, string $deviceName): array
    {
        try {
            Log::info('Login attempt for email: ' . ($credentials['email'] ?? 'null'));
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                Log::warning('User not found: ' . ($credentials['email'] ?? 'null'));
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            if (!$user->is_active) {
                Log::warning('User account inactive: ' . $user->email);
                throw ValidationException::withMessages([
                    'email' => ['Ce compte est désactivé.'],
                ]);
            }

            Log::info('Checking password for user: ' . $user->email);
            if (!Hash::check($credentials['password'], $user->mot_de_passe)) {
                Log::warning('Password check failed for user: ' . $user->email);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants sont incorrects.'],
                ]);
            }

            Log::info('Password check successful for user: ' . $user->email);

            return DB::transaction(function () use ($user, $deviceName) {
                $token = $user->createToken($deviceName);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'login',
                    'entity_name' => 'auth',
                    'entity_id' => $user->id,
                    'module' => 'auth',
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return [
                    'token' => $token->plainTextToken,
                    'user' => $this->getUserWithRolesAndPermissions($user),
                ];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion: ' . $e->getMessage());
            throw new AuthenticationException('Une erreur est survenue lors de la connexion.');
        }
    }

    /**
     * Déconnecter l'utilisateur (révoquer le token actuel).
     *
     * @param User $user
     * @throws \Exception
     */
    public function logout(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $user->currentAccessToken()?->delete();

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'logout',
                    'entity_name' => 'auth',
                    'entity_id' => $user->id,
                    'module' => 'auth',
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir l'utilisateur avec ses rôles et permissions.
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getUserWithRolesAndPermissions(User $user): array
    {
        $user->load(['roles.permissions']);

        $roles = $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'nom' => $role->nom,
                'niveau_hierarchique' => $role->niveau_hierarchique,
            ];
        });

        $permissions = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = [
                    'id' => $permission->id,
                    'nom' => $permission->nom,
                    'module' => $permission->module,
                ];
            }
        }

        // Modules accessibles uniques
        $modules = array_unique(array_column($permissions, 'module'));

        return [
            'id' => $user->id,
            'email' => $user->email,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'departement' => $user->departement,
            'photo_profil' => $user->photo_profil,
            'date_embauche' => $user->date_embauche,
            'is_active' => $user->is_active,
            'roles' => $roles,
            'permissions' => array_values(array_unique($permissions, SORT_REGULAR)),
            'modules_accessibles' => array_values($modules),
        ];
    }

    /**
     * Changer le mot de passe de l'utilisateur.
     *
     * @param User $user
     * @param string $ancienMotDePasse
     * @param string $nouveauMotDePasse
     * @throws ValidationException
     * @throws \Exception
     */
    public function changePassword(User $user, string $ancienMotDePasse, string $nouveauMotDePasse): void
    {
        try {
            if (!Hash::check($ancienMotDePasse, $user->mot_de_passe)) {
                throw ValidationException::withMessages([
                    'ancien_mot_de_passe' => ['Le mot de passe actuel est incorrect.'],
                ]);
            }

            DB::transaction(function () use ($user, $nouveauMotDePasse) {
                $user->update([
                    'mot_de_passe' => Hash::make($nouveauMotDePasse),
                ]);

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'change_password',
                    'entity_name' => 'auth',
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de mot de passe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Générer un mot de passe temporaire sécurisé de 10 caractères.
     *
     * @return string
     */
    public function generateTemporaryPassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < 10; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }
}
